"""Compare an update intent to a snapshot; a match is evidence, not an operation receipt."""
import hashlib
import json


FIELDS = {'title', 'content', 'excerpt'}


def digest(value):
    raw = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(',', ':'), allow_nan=False)
    return hashlib.sha256(raw.encode('utf-8')).hexdigest()


def make_intent(post_id, body):
    if type(post_id) is not int or post_id <= 0 or not isinstance(body, dict) or body.get('terms'):
        return None
    fields, meta = body.get('fields', {}), body.get('meta', {})
    if not isinstance(fields, dict) or not isinstance(meta, dict) or not (fields or meta):
        return None
    if not set(fields).issubset(FIELDS) or any(not isinstance(v, str) for v in fields.values()):
        return None
    if any(not isinstance(k, str) for k in meta):
        return None
    desired = {'fields': fields, 'meta': {k: {'present': v is not None, 'value': v} for k, v in meta.items()}}
    try:
        checksum = digest(desired)
    except (TypeError, ValueError):
        return None
    return {'target': post_id, 'fields': sorted(fields), 'meta': sorted(meta), 'sha256': checksum}


def verify_intent(intent, snapshot):
    if not isinstance(intent, dict) or not isinstance(snapshot, dict) or snapshot.get('id') != intent.get('target'):
        return 'unknown'
    fields, meta = intent.get('fields'), intent.get('meta')
    if (not isinstance(fields, list) or not isinstance(meta, list) or
            any(not isinstance(k, str) or k not in FIELDS for k in fields) or
            any(not isinstance(k, str) for k in meta) or
            not isinstance(snapshot.get('meta'), dict) or
            any(k not in snapshot or not isinstance(snapshot[k], str) for k in fields)):
        return 'unknown'
    projected = {'fields': {k: snapshot[k] for k in fields},
                 'meta': {k: {'present': k in snapshot['meta'], 'value': snapshot['meta'].get(k)} for k in meta}}
    try:
        return 'match' if digest(projected) == intent.get('sha256') else 'mismatch'
    except (TypeError, ValueError):
        return 'unknown'
