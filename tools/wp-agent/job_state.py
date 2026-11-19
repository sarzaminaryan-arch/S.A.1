"""Lightweight, locked run journal stored outside the Git checkout.

A local disk journal is durable only while that disk survives. GitHub-hosted
runners are ephemeral and are deliberately rejected; a persistent self-hosted
runner requires an explicitly configured state directory outside the checkout.
"""
import fcntl
import json
import os
from pathlib import Path
import tempfile

from write_verification import verify_intent


class StateError(Exception):
    pass


READ_ONLY = {'ping', 'list', 'get', 'backups', 'probe', 'inventory', 'seo-scan',
             'featured-audit', 'storage', 'index-gate', 'relations-audit'}


class Journal:
    def __init__(self, repo):
        configured = os.environ.get('SA_WP_AGENT_STATE_DIR')
        if os.environ.get('GITHUB_ACTIONS') == 'true':
            if os.environ.get('RUNNER_ENVIRONMENT') != 'self-hosted' or not configured:
                raise StateError('A persistent self-hosted runner and SA_WP_AGENT_STATE_DIR are required; GitHub-hosted disks are ephemeral.')
        self.dir = Path(configured or (Path.home() / '.local/state/sa-wp-agent')).expanduser().resolve()
        if self.dir == repo or repo in self.dir.parents:
            raise StateError('State directory must be outside the Git checkout.')
        self.path = self.dir / 'state.json'

    def __enter__(self):
        self.dir.mkdir(parents=True, exist_ok=True, mode=0o700)
        self.lock = (self.dir / 'state.lock').open('a+')
        try:
            fcntl.flock(self.lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            self.data = json.loads(self.path.read_text(encoding='utf-8')) if self.path.exists() else {'version': 1, 'active': None, 'finished': {}}
            if (not isinstance(self.data, dict) or self.data.get('version') != 1 or
                    not isinstance(self.data.get('finished'), dict) or
                    (self.data.get('active') is not None and not isinstance(self.data.get('active'), dict))):
                raise StateError('Invalid state journal; manual review required.')
            active = self.data['active']
            if active is not None and (
                not isinstance(active.get('job'), str) or not active['job'] or
                active.get('phase') not in ('between', 'executing', 'restoring') or
                type(active.get('untracked')) is not bool or
                type(active.get('rollback_on_fail')) is not bool or
                not isinstance(active.get('backups'), list) or
                any(type(b) is not int or b <= 0 for b in active['backups']) or
                (active['phase'] == 'executing' and not isinstance(active.get('command'), str))
            ):
                raise StateError('Invalid active state; manual review required.')
        except Exception as exc:
            self.lock.close()
            if isinstance(exc, StateError): raise
            raise StateError(f'Cannot lock or read state journal: {type(exc).__name__}') from exc
        return self

    def __exit__(self, *args):
        self.lock.close()

    def save(self):
        # fsync file and directory so a successful checkpoint survives a crash.
        fd, name = tempfile.mkstemp(prefix='.state-', dir=self.dir)
        try:
            with os.fdopen(fd, 'w', encoding='utf-8') as f:
                json.dump(self.data, f, ensure_ascii=False)
                f.flush()
                os.fsync(f.fileno())
            os.replace(name, self.path)
            dirfd = os.open(self.dir, os.O_RDONLY)
            try: os.fsync(dirfd)
            finally: os.close(dirfd)
        finally:
            if os.path.exists(name): os.unlink(name)

    def finish(self, status):
        active = self.data['active']
        self.data['finished'][active['job']] = status
        self.data['active'] = None
        self.save()

    def recover(self, client, requested_job=None, fingerprint=None, allow_rollback=False):
        active = self.data['active']
        if not active: return
        # After a crash, only a wholly read-only unchanged job can be safely replayed.
        # Old backup IDs alone cannot prove nobody else edited the site since the crash.
        if not allow_rollback:
            if (active.get('read_only_job') is True and active.get('job') == requested_job
                    and active.get('fingerprint') == fingerprint and active.get('phase') != 'restoring'):
                self.data['active'] = None
                self.save()
                return
            intent = active.get('intent')
            if (active.get('phase') == 'executing' and active.get('command') == 'update'
                    and isinstance(intent, dict) and type(intent.get('target')) is int
                    and intent['target'] > 0):
                try:
                    snapshot = client.call('GET', f"/post/{intent['target']}")
                    outcome = verify_intent(intent, snapshot)
                except Exception:
                    outcome = 'unknown'
                active['verification'] = outcome
                self.save()
            # A matching snapshot does not prove this job applied the update, nor
            # recover a backup ID. Never replay/rollback without an operation receipt.
            raise StateError('Interrupted job may have changed the site; manual reconciliation required.')
        if (active.get('phase') == 'restoring' or active.get('untracked') or
                (active.get('phase') == 'executing' and active.get('command') not in READ_ONLY)):
            raise StateError('Previous write/restore has an unknown outcome; stop for manual reconciliation.')
        backups = active.get('backups')
        if not isinstance(backups, list) or any(not isinstance(b, int) or b <= 0 for b in backups):
            raise StateError('Invalid backup state; manual review required.')
        if backups and not active.get('rollback_on_fail'):
            raise StateError('Previous interrupted job did not authorize rollback; manual review required.')
        while backups:
            bid = backups[-1]
            active['phase'] = 'restoring'
            active['restoring_backup'] = bid
            self.save()
            try:
                client.call('POST', f'/restore/{bid}', body={})
            except Exception as exc:
                raise StateError(f'Restore outcome uncertain ({type(exc).__name__}); manual review required.') from exc
            backups.pop()
            active['phase'] = 'between'
            active.pop('restoring_backup', None)
            self.save()
        self.finish('recovered')
        raise StateError('Interrupted job was recovered; review it before starting another job.')

    def start(self, job, rollback_on_fail, fingerprint, read_only_job):
        if job in self.data['finished']:
            raise StateError('Job already recorded in the independent state journal; do not rerun it.')
        self.data['active'] = {'job': job, 'phase': 'between', 'backups': [],
                               'untracked': False, 'rollback_on_fail': bool(rollback_on_fail),
                               'fingerprint': fingerprint, 'read_only_job': read_only_job}
        self.save()

    def before_step(self, command):
        active = self.data['active']
        active['phase'], active['command'] = 'executing', command
        self.save()

    def after_step(self, command, result):
        active = self.data['active']
        if command not in READ_ONLY:
            bid = result.get('backup_id') if command == 'update' and isinstance(result, dict) else None
            if isinstance(bid, int) and bid > 0:
                active['backups'].append(bid)
            else:
                active['untracked'] = True
        active['phase'] = 'between'
        self.save()
