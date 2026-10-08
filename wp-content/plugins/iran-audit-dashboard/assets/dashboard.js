(function () {
	'use strict';

	const config = window.IAAD_CONFIG || {};
	const app = document.getElementById('iaad-app');
	if (!app || !config.apiRoot) return;

	const state = {
		status: null,
		activeTab: 'overview',
		posts: [],
		postPage: 1,
		postTotal: 0,
		postTotalPages: 1,
		selectedPosts: new Set(),
		currentJob: null,
		pollTimer: null,
		filters: { search: '', status: '', profile: '' },
	};

	function element(tag, className, text) {
		const item = document.createElement(tag);
		if (className) item.className = className;
		if (text !== undefined && text !== null) item.textContent = String(text);
		return item;
	}

	function button(label, className, handler, disabled) {
		const item = element('button', className || 'button', label);
		item.type = 'button';
		item.disabled = Boolean(disabled);
		item.addEventListener('click', handler);
		return item;
	}

	function cell(row, text, className) {
		const item = element('td', className || '', text === null || text === undefined || text === '' ? '—' : text);
		row.appendChild(item);
		return item;
	}

	function apiUrl(path, params) {
		const route = String(path).replace(/^\/+|\/+$/g, '');
		const url = new URL(String(config.apiRoot), window.location.origin);
		if (url.searchParams.has('rest_route')) {
			const restRoute = String(url.searchParams.get('rest_route') || '').replace(/\/+$/, '');
			url.searchParams.set('rest_route', restRoute + '/' + route);
		} else {
			url.pathname = url.pathname.replace(/\/+$/, '') + '/' + route;
		}
		if (params) {
			Object.keys(params).forEach(function (key) {
				if (params[key] !== '' && params[key] !== null && params[key] !== undefined) {
					url.searchParams.set(key, String(params[key]));
				}
			});
		}
		return url;
	}

	async function request(path, options) {
		const settings = options || {};
		const headers = new Headers(settings.headers || {});
		headers.set('X-WP-Nonce', config.nonce || '');
		if (settings.body && !headers.has('Content-Type')) headers.set('Content-Type', 'application/json');
		let response;
		try {
			response = await fetch(apiUrl(path, settings.params), {
				method: settings.method || 'GET',
				credentials: 'same-origin',
				headers: headers,
				body: settings.body ? JSON.stringify(settings.body) : undefined,
			});
		} catch (error) {
			throw new Error(config.i18n?.networkError || 'ارتباط با API برقرار نشد.');
		}
		const contentType = response.headers.get('content-type') || '';
		let data = null;
		if (settings.responseType === 'blob' && response.ok) {
			data = await response.blob();
		} else if (contentType.indexOf('application/json') !== -1) {
			try { data = await response.json(); } catch (error) { data = null; }
		} else {
			try { data = await response.text(); } catch (error) { data = null; }
		}
		if (!response.ok) {
			const message = data && typeof data === 'object' && data.message ? data.message : (config.i18n?.error || 'درخواست ناموفق بود.');
			const failure = new Error(message);
			failure.status = response.status;
			failure.code = data && data.code ? data.code : '';
			throw failure;
		}
		return { data: data, headers: response.headers, response: response };
	}

	function showMessage(parent, text, type) {
		const box = element('div', 'iaad-notice ' + (type || 'info'), text);
		box.setAttribute('role', type === 'error' ? 'alert' : 'status');
		parent.appendChild(box);
		return box;
	}

	function statusBadge(status) {
		const labels = {
			verified: 'تأییدشده', warning: 'نیازمند توجه', issue: 'دارای ایراد',
			insufficient: 'پوشش ناکافی', unaudited: 'ممیزی‌نشده', queued: 'در صف',
			running: 'در حال اجرا', completed: 'تکمیل‌شده', failed: 'ناموفق', cancelled: 'لغوشده',
		};
		return element('span', 'iaad-badge iaad-status-' + String(status || 'unknown'), labels[status] || status || 'نامشخص');
	}

	function formatNumber(value, decimals) {
		if (value === null || value === undefined || value === '') return '—';
		const number = Number(value);
		if (!Number.isFinite(number)) return '—';
		return number.toLocaleString('fa-IR', { maximumFractionDigits: decimals === undefined ? 1 : decimals });
	}

	function startShell() {
		app.replaceChildren();
		const shell = element('div', 'iaad-shell');
		const nav = element('nav', 'iaad-tabs');
		nav.setAttribute('aria-label', 'بخش‌های داشبورد ممیزی');
		[
			['overview', 'نمای کلی'], ['posts', 'نوشته‌ها'], ['claims', 'ادعاهای نیازمند بررسی'], ['rules', 'قواعد'],
		].forEach(function (tab) {
			const item = button(tab[1], 'iaad-tab', function () { selectTab(tab[0]); });
			item.dataset.tab = tab[0];
			nav.appendChild(item);
		});
		shell.appendChild(nav);
		const status = element('section', 'iaad-engine-status');
		status.setAttribute('aria-label', 'وضعیت موتور ممیزی');
		const statusLine = element('div', 'iaad-engine-status-line');
		statusLine.appendChild(element('strong', '', 'Engine ' + (state.status.engine_version || '—')));
		statusLine.appendChild(element('span', '', 'API ' + (state.status.api_version || '—')));
		statusLine.appendChild(element('span', '', 'قواعد ' + (state.status.rules_version || '—') + ' (' + formatNumber(state.status.rule_count, 0) + ' قانون)'));
		status.appendChild(statusLine);
		if (!state.status.rules_valid) showMessage(status, state.status.rules_error || 'فایل قواعد معتبر نیست؛ اجرای ممیزی متوقف است.', 'error');
		if (!state.status.database?.ready) showMessage(status, 'جدول‌های اختصاصی موتور آماده نیستند. مدیر سایت باید مهاجرت افزونه را بررسی کند.', 'error');
		if (state.status.profile_mapping && !state.status.profile_mapping.complete) {
			const missing = (state.status.profile_mapping.unmapped || []).map(function (item) { return item.label || item.post_type; }).join('، ');
			showMessage(status, 'نگاشت پروفایل همهٔ نوع‌نوشته‌های عمومی کامل نیست. موارد بدون نگاشت: ' + (missing || 'نامشخص') + '؛ قوانین وابسته با «اطلاعات ناکافی» گزارش می‌شوند.', 'warning');
		}
		if (!state.status.checks?.rendered_html_enabled) showMessage(status, 'بررسی‌های وابسته به HTML رندرشده خاموش‌اند و نتیجهٔ این قواعد «اطلاعات ناکافی» است؛ برای فعال‌سازی، ابتدا staging را تأیید کنید.', 'warning');
		if (!state.status.checks?.geography_dataset_present) showMessage(status, 'دیتاست تقسیمات کشوری هنوز به افزونه اضافه نشده است؛ نتایج جغرافیایی کامل نیست.', 'warning');
		if (!state.status.checks?.external_link_checks_enabled) showMessage(status, 'بررسی HTTP لینک‌های بیرونی در این نسخه اجرا نمی‌شود؛ هیچ درخواستی به دامنه‌های خارجی ارسال نمی‌شود.', 'warning');
		shell.appendChild(status);
		const job = element('section', 'iaad-job-region');
		job.id = 'iaad-job-region';
		shell.appendChild(job);
		const content = element('main', 'iaad-content');
		content.id = 'iaad-content';
		shell.appendChild(content);
		app.appendChild(shell);
		selectTab(state.activeTab);
	}

	function selectTab(name) {
		state.activeTab = name;
		app.querySelectorAll('.iaad-tab').forEach(function (tab) {
			const active = tab.dataset.tab === name;
			tab.classList.toggle('is-active', active);
			tab.setAttribute('aria-current', active ? 'page' : 'false');
		});
		const content = document.getElementById('iaad-content');
		if (!content) return;
		content.replaceChildren();
		if (name === 'overview') loadOverview(content);
		else if (name === 'posts') renderPosts(content);
		else if (name === 'claims') renderClaims(content);
		else if (name === 'rules') renderRules(content);
	}

	function card(label, value, hint) {
		const item = element('div', 'iaad-card');
		item.appendChild(element('span', 'iaad-card-label', label));
		item.appendChild(element('strong', 'iaad-card-value', value));
		if (hint) item.appendChild(element('small', 'iaad-card-hint', hint));
		return item;
	}

	async function loadOverview(content) {
		content.appendChild(element('h2', '', 'نمای کلی'));
		const reload = button('به‌روزرسانی', 'button', function () { selectTab('overview'); });
		content.appendChild(reload);
		const summaryHost = element('div', 'iaad-cards');
		content.appendChild(summaryHost);
		const columns = element('div', 'iaad-columns');
		const issuesHost = element('section', 'iaad-panel');
		const gapsHost = element('section', 'iaad-panel');
		columns.append(issuesHost, gapsHost);
		content.appendChild(columns);
		try {
			const results = await Promise.all([
				request('stats/summary'), request('stats/distribution'), request('stats/top-issues', { params: { limit: 8 } }), request('stats/coverage-gaps'),
			]);
			if (!content.isConnected) return;
			const summary = results[0].data || {};
			const distribution = results[1].data || {};
			const topIssues = Array.isArray(results[2].data) ? results[2].data : [];
			const gaps = Array.isArray(results[3].data) ? results[3].data : [];
			summaryHost.replaceChildren(
				card('گزارش‌های ثبت‌شده', formatNumber(summary.reports, 0), 'فقط آخرین گزارش هر نوشته در آمار وضعیت‌ها شمرده می‌شود.'),
				card('میانگین امتیاز', formatNumber(summary.average_score), 'از ۱۰۰، بر اساس گزارش‌های دارای امتیاز'),
				card('میانگین پوشش', formatNumber(summary.average_coverage) + '%', 'درصد قواعدی که دادهٔ کافی داشته‌اند'),
				card('کارهای فعال صف', formatNumber(state.status.queue?.active_jobs, 0), 'وضعیت فعلی هنگام اتصال')
			);
			const distributionPanel = element('section', 'iaad-panel');
			distributionPanel.appendChild(element('h3', '', 'توزیع وضعیت‌ها'));
			const distributionList = element('ul', 'iaad-distribution');
			[['verified', 'تأییدشده'], ['warning', 'نیازمند توجه'], ['issue', 'دارای ایراد'], ['insufficient', 'پوشش ناکافی']].forEach(function (entry) {
				const li = element('li', '');
				li.appendChild(statusBadge(entry[0]));
				li.appendChild(element('strong', '', formatNumber(distribution[entry[0]] || 0, 0)));
				distributionList.appendChild(li);
			});
			distributionPanel.appendChild(distributionList);
			content.insertBefore(distributionPanel, columns);
			renderTopIssues(issuesHost, topIssues);
			renderCoverageGaps(gapsHost, gaps);
			const rankingPanel = element('section', 'iaad-panel iaad-ranking-panel');
			content.appendChild(rankingPanel);
			renderRankings(rankingPanel);
		} catch (error) {
			if (content.isConnected) showMessage(content, error.message || config.i18n?.error || 'بارگذاری خلاصه ناموفق بود.', 'error');
		}
	}

	async function renderRankings(host) {
		host.replaceChildren(element('h3', '', 'رتبه‌بندی'));
		const controls = element('div', 'iaad-filters');
		const group = element('select', '');
		[['post', 'نوشته‌ها'], ['province', 'استان‌ها'], ['county', 'شهرها']].forEach(function (entry) {
			const option = element('option', '', entry[1]); option.value = entry[0]; group.appendChild(option);
		});
		const order = element('select', '');
		[['desc', 'بیشترین امتیاز'], ['asc', 'کمترین امتیاز']].forEach(function (entry) {
			const option = element('option', '', entry[1]); option.value = entry[0]; order.appendChild(option);
		});
		const resultsHost = element('div', 'iaad-ranking-results');
		const load = async function () {
			resultsHost.replaceChildren(element('p', 'iaad-loading-text', config.i18n?.loading || 'در حال بارگذاری…'));
			try {
				const result = await request('stats/rankings', { params: { group_by: group.value, order: order.value } });
				const rows = Array.isArray(result.data) ? result.data : [];
				resultsHost.replaceChildren();
				if (!rows.length) { resultsHost.appendChild(element('p', '', config.i18n?.noData || 'داده‌ای برای رتبه‌بندی وجود ندارد.')); return; }
				const table = element('table', 'widefat striped iaad-table');
				const header = element('tr', '');
				if (group.value === 'post') ['نوشته', 'شناسه', 'وضعیت', 'امتیاز', 'پوشش'].forEach(function (label) { header.appendChild(element('th', '', label)); });
				else ['گروه', 'تعداد نوشته', 'میانگین امتیاز', 'میانگین پوشش'].forEach(function (label) { header.appendChild(element('th', '', label)); });
				table.appendChild(header);
				rows.forEach(function (item) {
					const tr = element('tr', '');
					if (group.value === 'post') {
						const title = element('td', ''); title.appendChild(button(item.post_title || '(بدون عنوان)', 'iaad-post-title', function () { showReport(item.post_id); })); tr.appendChild(title);
						cell(tr, item.post_id); const statusCell = element('td', ''); statusCell.appendChild(statusBadge(item.status)); tr.appendChild(statusCell); cell(tr, formatNumber(item.score)); cell(tr, formatNumber(item.coverage) + '%');
					} else {
						cell(tr, item.group); cell(tr, formatNumber(item.posts, 0)); cell(tr, formatNumber(item.score)); cell(tr, formatNumber(item.coverage) + '%');
					}
					table.appendChild(tr);
				});
				resultsHost.appendChild(table);
			} catch (error) { resultsHost.replaceChildren(); showMessage(resultsHost, error.message || config.i18n?.error || 'بارگذاری رتبه‌بندی ناموفق بود.', 'error'); }
		};
		group.addEventListener('change', load); order.addEventListener('change', load);
		controls.append(group, order); host.append(controls, resultsHost);
		await load();
	}

	function renderTopIssues(host, rows) {
		host.replaceChildren(element('h3', '', 'پرتکرارترین ایرادها'));
		if (!rows.length) { host.appendChild(element('p', '', config.i18n?.noData || 'داده‌ای وجود ندارد.')); return; }
		const table = element('table', 'widefat striped iaad-table');
		const head = element('tr', '');
		['شناسه قانون', 'دسته', 'شدت', 'تکرار'].forEach(function (label) { head.appendChild(element('th', '', label)); });
		table.appendChild(head);
		rows.forEach(function (row) {
			const tr = element('tr', '');
			cell(tr, row.rule_id); cell(tr, row.category); cell(tr, row.severity); cell(tr, formatNumber(row.occurrences, 0));
			table.appendChild(tr);
		});
		host.appendChild(table);
	}

	function renderCoverageGaps(host, rows) {
		host.replaceChildren(element('h3', '', 'شکاف‌های پوشش'));
		if (!rows.length) { host.appendChild(element('p', '', config.i18n?.noData || 'داده‌ای وجود ندارد.')); return; }
		const table = element('table', 'widefat striped iaad-table');
		const head = element('tr', '');
		['قانون / منبع', 'تعداد گزارش‌ها', 'دلیل'].forEach(function (label) { head.appendChild(element('th', '', label)); });
		table.appendChild(head);
		rows.forEach(function (row) {
			const tr = element('tr', '');
			cell(tr, row.rule_id); cell(tr, row.affected_reports === null ? '—' : formatNumber(row.affected_reports, 0)); cell(tr, row.reason);
			table.appendChild(tr);
		});
		host.appendChild(table);
	}

	function renderPosts(content) {
		content.appendChild(element('h2', '', 'نوشته‌ها'));
		const form = element('form', 'iaad-filters');
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			state.filters.search = form.elements.search.value.trim();
			state.filters.status = form.elements.status.value;
			state.filters.profile = form.elements.profile.value;
			state.postPage = 1;
			loadPosts(content, tableHost, pager, queueButton);
		});
		const search = element('input', 'regular-text');
		search.type = 'search'; search.name = 'search'; search.placeholder = 'جست‌وجوی عنوان یا متن'; search.value = state.filters.search;
		const status = element('select', ''); status.name = 'status';
		[['', 'همه وضعیت‌ها'], ['verified', 'تأییدشده'], ['warning', 'نیازمند توجه'], ['issue', 'دارای ایراد'], ['insufficient', 'پوشش ناکافی'], ['unaudited', 'ممیزی‌نشده']].forEach(function (option) {
			const opt = element('option', '', option[1]); opt.value = option[0]; opt.selected = state.filters.status === option[0]; status.appendChild(opt);
		});
		const profile = element('select', ''); profile.name = 'profile';
		const profileOptions = [['', 'همهٔ پروفایل‌ها']].concat(Object.keys(state.status.profile_mapping?.map || {}).map(function (type) {
			const value = state.status.profile_mapping.map[type];
			const display = value === 'county' ? 'شهر (city)' : value;
			return [value, display + ' (' + type + ')'];
		}));
		const uniqueProfiles = new Map();
		profileOptions.forEach(function (option) { if (!uniqueProfiles.has(option[0])) uniqueProfiles.set(option[0], option[1]); });
		uniqueProfiles.forEach(function (label, value) { const opt = element('option', '', label); opt.value = value; opt.selected = state.filters.profile === value; profile.appendChild(opt); });
		form.append(search, status, profile, button('جست‌وجو', 'button button-primary', function () { form.requestSubmit(); }));
		content.appendChild(form);
		const actions = element('div', 'iaad-list-actions');
		const queueButton = button('ممیزی نوشته‌های انتخاب‌شده', 'button button-primary', function () { queueSelected(); }, true);
		const exportButton = button('دریافت CSV', 'button', downloadCsv);
		actions.append(queueButton, exportButton);
		content.appendChild(actions);
		const tableHost = element('div', 'iaad-post-table');
		content.appendChild(tableHost);
		const pager = element('div', 'iaad-pager');
		content.appendChild(pager);
		loadPosts(content, tableHost, pager, queueButton);
	}

	async function loadPosts(content, tableHost, pager, queueButton) {
		tableHost.replaceChildren(element('p', 'iaad-loading-text', config.i18n?.loading || 'در حال بارگذاری…'));
		pager.replaceChildren();
		const params = { page: state.postPage, per_page: 20, search: state.filters.search, status: state.filters.status, profile: state.filters.profile };
		try {
			const result = await request('posts', { params: params });
			if (!content.isConnected) return;
			state.posts = Array.isArray(result.data) ? result.data : [];
			state.postTotal = Number(result.headers.get('X-WP-Total') || 0);
			state.postTotalPages = Math.max(1, Number(result.headers.get('X-WP-TotalPages') || 1));
			renderPostTable(tableHost, state.posts, queueButton);
			renderPager(pager, content, tableHost, pager, queueButton);
		} catch (error) {
			if (content.isConnected) showMessage(tableHost, error.message || config.i18n?.error || 'بارگذاری نوشته‌ها ناموفق بود.', 'error');
		}
	}

	function renderPostTable(host, rows, queueButton) {
		host.replaceChildren();
		if (!rows.length) { host.appendChild(element('p', '', config.i18n?.noData || 'نوشته‌ای پیدا نشد.')); queueButton.disabled = true; return; }
		const table = element('table', 'widefat striped iaad-table iaad-posts-table');
		const thead = element('thead', ''); const header = element('tr', '');
		['انتخاب', 'عنوان', 'نوع', 'پروفایل', 'وضعیت', 'امتیاز', 'پوشش', 'آخرین ممیزی', 'عملیات'].forEach(function (label) { header.appendChild(element('th', '', label)); });
		thead.appendChild(header); table.appendChild(thead);
		const body = element('tbody', '');
		rows.forEach(function (post) {
			const tr = element('tr', '');
			const selectCell = element('td', '');
			const checkbox = element('input', ''); checkbox.type = 'checkbox'; checkbox.checked = state.selectedPosts.has(Number(post.post_id));
			checkbox.setAttribute('aria-label', 'انتخاب ' + (post.post_title || 'نوشته'));
			checkbox.addEventListener('change', function () {
				const id = Number(post.post_id);
				if (checkbox.checked) state.selectedPosts.add(id); else state.selectedPosts.delete(id);
				queueButton.disabled = state.selectedPosts.size === 0;
			});
			selectCell.appendChild(checkbox); tr.appendChild(selectCell);
			const titleCell = element('td', '');
			const titleButton = button(post.post_title || '(بدون عنوان)', 'iaad-post-title', function () { showReport(post.post_id); });
			titleCell.appendChild(titleButton);
			if (post.post_url && /^https?:\/\//i.test(post.post_url)) {
				const link = element('a', 'iaad-permalink', 'نمایش'); link.href = post.post_url; link.target = '_blank'; link.rel = 'noopener noreferrer'; titleCell.appendChild(link);
			}
			tr.appendChild(titleCell);
			cell(tr, post.post_type); cell(tr, post.profile === 'county' ? 'شهر (city)' : (post.profile || 'بدون نگاشت'));
			const statusCell = element('td', ''); statusCell.appendChild(statusBadge(post.status)); tr.appendChild(statusCell);
			cell(tr, post.score === null ? '—' : formatNumber(post.score)); cell(tr, formatNumber(post.coverage) + '%');
			cell(tr, post.audited_at ? new Date(post.audited_at).toLocaleString('fa-IR') : '—');
			const actionCell = element('td', '');
			actionCell.appendChild(button('ممیزی', 'button button-small', function () { queueOne(post.post_id); }));
			tr.appendChild(actionCell); body.appendChild(tr);
		});
		table.appendChild(body); host.appendChild(table);
		queueButton.disabled = state.selectedPosts.size === 0;
	}

	function renderPager(pager, content, tableHost, pagerAgain, queueButton) {
		pager.replaceChildren();
		pager.appendChild(element('span', '', 'صفحهٔ ' + formatNumber(state.postPage, 0) + ' از ' + formatNumber(state.postTotalPages, 0) + ' — ' + formatNumber(state.postTotal, 0) + ' نوشته'));
		const previous = button('قبلی', 'button', function () { if (state.postPage > 1) { state.postPage--; loadPosts(content, tableHost, pagerAgain, queueButton); } }, state.postPage <= 1);
		const next = button('بعدی', 'button', function () { if (state.postPage < state.postTotalPages) { state.postPage++; loadPosts(content, tableHost, pagerAgain, queueButton); } }, state.postPage >= state.postTotalPages);
		pager.append(previous, next);
	}

	async function queueOne(postId) {
		if (!window.confirm(config.i18n?.confirmAudit || 'ممیزی آغاز شود؟')) return;
		try {
			const result = await request('posts/' + encodeURIComponent(postId) + '/audit', { method: 'POST', body: {} });
			watchJob(result.data);
		} catch (error) { alert(error.message); }
	}

	async function queueSelected() {
		const ids = Array.from(state.selectedPosts).slice(0, 100);
		if (!ids.length || !window.confirm(config.i18n?.confirmAudit || 'ممیزی آغاز شود؟')) return;
		try {
			const result = await request('queue', { method: 'POST', body: { post_ids: ids } });
			watchJob(result.data);
			state.selectedPosts.clear();
			if (state.activeTab === 'posts') selectTab('posts');
		} catch (error) { alert(error.message); }
	}

	function watchJob(job) {
		if (!job || !job.job_id) return;
		state.currentJob = job;
		renderJob(job);
		if (state.pollTimer) window.clearTimeout(state.pollTimer);
		if (['completed', 'failed', 'cancelled'].indexOf(job.status) === -1) pollJob(job.job_id);
	}

	function renderJob(job) {
		const host = document.getElementById('iaad-job-region');
		if (!host) return;
		host.replaceChildren();
		const panel = element('div', 'iaad-job-panel');
		const details = element('div', 'iaad-job-details');
		details.appendChild(element('strong', '', 'کار ' + job.job_id));
		details.appendChild(statusBadge(job.status));
		details.appendChild(element('span', '', 'پیشرفت: ' + formatNumber(job.progress, 0) + '%'));
		if (job.current_step) details.appendChild(element('span', '', 'مرحله: ' + job.current_step));
		panel.appendChild(details);
		const progress = element('progress', ''); progress.max = 100; progress.value = Math.min(100, Math.max(0, Number(job.progress) || 0)); progress.setAttribute('aria-label', 'درصد پیشرفت'); panel.appendChild(progress);
		if (job.error) panel.appendChild(showMessage(panel, job.error, 'error'));
		if (['queued', 'running'].indexOf(job.status) !== -1) panel.appendChild(button('لغو کار', 'button', function () { cancelJob(job.job_id); }));
		host.appendChild(panel);
	}

	async function pollJob(jobId) {
		try {
			const result = await request('jobs/' + encodeURIComponent(jobId));
			state.currentJob = result.data;
			renderJob(result.data);
			if (['completed', 'failed', 'cancelled'].indexOf(result.data.status) === -1) {
				state.pollTimer = window.setTimeout(function () { pollJob(jobId); }, 2000);
			} else if (state.activeTab === 'posts') {
				const content = document.getElementById('iaad-content');
				const host = content && content.querySelector('.iaad-post-table');
				if (content && host) selectTab('posts');
			}
		} catch (error) {
			const host = document.getElementById('iaad-job-region');
			if (host) showMessage(host, error.message || 'خواندن وضعیت کار ناموفق بود.', 'error');
		}
	}

	async function cancelJob(jobId) {
		if (!window.confirm('این کار از صف لغو شود؟')) return;
		try {
			const result = await request('jobs/' + encodeURIComponent(jobId), { method: 'DELETE' });
			watchJob(result.data);
		} catch (error) { alert(error.message); }
	}

	async function showReport(postId) {
		const content = document.getElementById('iaad-content');
		if (!content) return;
		const panel = element('section', 'iaad-report-panel');
		panel.appendChild(element('h3', '', 'گزارش نوشته #' + postId));
		panel.appendChild(element('p', 'iaad-loading-text', config.i18n?.loading || 'در حال بارگذاری…'));
		content.prepend(panel);
		try {
			const result = await request('posts/' + encodeURIComponent(postId) + '/report');
			const report = result.data || {};
			panel.replaceChildren();
			const head = element('div', 'iaad-report-head');
			head.appendChild(element('h3', '', report.post_title || 'گزارش ممیزی'));
			const close = button('بستن', 'button', function () { panel.remove(); });
			head.appendChild(close); panel.appendChild(head);
			const overall = report.overall || {};
			const reportCards = element('div', 'iaad-cards');
			reportCards.append(card('امتیاز', formatNumber(overall.score), 'از ۱۰۰'), card('وضعیت', (overall.status || '—'), ''), card('پوشش', formatNumber(overall.coverage) + '%', ''));
			panel.appendChild(reportCards);
			panel.appendChild(element('p', 'iaad-report-meta', 'نسخهٔ قواعد: ' + (report.rules_version || '—') + '؛ ممیزی: ' + (report.audited_at ? new Date(report.audited_at).toLocaleString('fa-IR') : '—')));
			const categories = Array.isArray(report.categories) ? report.categories : [];
			if (categories.length) {
				panel.appendChild(element('h4', '', 'وضعیت دسته‌ها'));
				const table = element('table', 'widefat striped iaad-table');
				const header = element('tr', ''); ['دسته', 'امتیاز', 'وضعیت', 'پوشش'].forEach(function (label) { header.appendChild(element('th', '', label)); }); table.appendChild(header);
				categories.forEach(function (category) { const tr = element('tr', ''); cell(tr, category.id); cell(tr, formatNumber(category.score)); const td = element('td', ''); td.appendChild(statusBadge(category.status)); tr.appendChild(td); cell(tr, formatNumber(category.coverage) + '%'); table.appendChild(tr); });
				panel.appendChild(table);
			}
			const issues = Array.isArray(report.issues) ? report.issues : [];
			panel.appendChild(element('h4', '', 'نتایج و شکاف‌های قوانین (' + formatNumber(issues.length, 0) + ')'));
			if (!issues.length) panel.appendChild(element('p', '', config.i18n?.noData || 'داده‌ای وجود ندارد.'));
			issues.forEach(function (issue) {
				const item = element('article', 'iaad-issue');
				const title = element('div', 'iaad-issue-title'); title.append(statusBadge(issue.status), element('strong', '', issue.rule_id + ' · ' + (issue.severity || 'info'))); item.appendChild(title);
				item.appendChild(element('p', '', issue.message || ''));
				if (issue.suggestion) item.appendChild(element('p', 'iaad-suggestion', 'پیشنهاد: ' + issue.suggestion));
				if (issue.needs_human_review) item.appendChild(element('small', 'iaad-human-review', 'نیازمند بازبینی انسانی'));
				panel.appendChild(item);
			});
			const historySection = element('section', 'iaad-history-section');
			historySection.appendChild(element('h4', '', 'تاریخچه و مقایسهٔ گزارش‌ها'));
			panel.appendChild(historySection);
			try {
				const historyResponse = await request('posts/' + encodeURIComponent(postId) + '/history');
				const history = Array.isArray(historyResponse.data) ? historyResponse.data : [];
				if (!history.length) { historySection.appendChild(element('p', '', config.i18n?.noData || 'تاریخچه‌ای ثبت نشده است.')); }
				else {
					const historyTable = element('table', 'widefat striped iaad-table');
					const historyHeader = element('tr', ''); ['گزارش', 'تاریخ', 'وضعیت', 'امتیاز', 'عملیات'].forEach(function (label) { historyHeader.appendChild(element('th', '', label)); }); historyTable.appendChild(historyHeader);
					history.forEach(function (entry) {
						const row = element('tr', ''); cell(row, entry.report_id); cell(row, entry.audited_at ? new Date(entry.audited_at).toLocaleString('fa-IR') : '—');
						const statusCell = element('td', ''); statusCell.appendChild(statusBadge(entry.status)); row.appendChild(statusCell); cell(row, formatNumber(entry.score));
						const actionCell = element('td', '');
						actionCell.appendChild(button('پیش‌نمایش', 'button button-small', function () { previewReport(postId, entry.report_id, panel); }));
						row.appendChild(actionCell); historyTable.appendChild(row);
					});
					historySection.appendChild(historyTable);
					if (history.length > 1) {
						const compareForm = element('div', 'iaad-filters');
						const first = element('select', ''); const second = element('select', '');
						history.forEach(function (entry) {
							const label = '#' + entry.report_id + ' · ' + (entry.audited_at ? new Date(entry.audited_at).toLocaleDateString('fa-IR') : '');
							[first, second].forEach(function (select) { const option = element('option', '', label); option.value = entry.report_id; select.appendChild(option); });
						});
						first.value = String(history[1].report_id); second.value = String(history[0].report_id);
						const diffHost = element('div', 'iaad-diff-results');
						compareForm.append(first, second, button('مقایسه', 'button', async function () {
							diffHost.replaceChildren(element('p', 'iaad-loading-text', config.i18n?.loading || 'در حال بارگذاری…'));
							try {
								const diffResponse = await request('posts/' + encodeURIComponent(postId) + '/diff', { params: { a: first.value, b: second.value } });
								diffHost.replaceChildren();
								[['resolved', 'رفع‌شده'], ['new', 'جدید'], ['unchanged', 'بدون تغییر']].forEach(function (group) {
									const entries = Array.isArray(diffResponse.data[group[0]]) ? diffResponse.data[group[0]] : [];
									const section = element('section', 'iaad-diff-group'); section.appendChild(element('strong', '', group[1] + ' (' + formatNumber(entries.length, 0) + ')'));
									entries.forEach(function (issue) { section.appendChild(element('p', '', issue.rule_id + ': ' + (issue.message || ''))); });
									diffHost.appendChild(section);
								});
							} catch (error) { diffHost.replaceChildren(); showMessage(diffHost, error.message || 'مقایسهٔ گزارش‌ها ناموفق بود.', 'error'); }
						}), diffHost);
						historySection.appendChild(compareForm);
					}
				}
			} catch (historyError) { showMessage(historySection, historyError.message || 'خواندن تاریخچه ناموفق بود.', 'error'); }
		} catch (error) {
			panel.replaceChildren(element('h3', '', 'دریافت گزارش ناموفق بود'), showMessage(panel, error.message || config.i18n?.error || 'گزارش در دسترس نیست.', 'error'));
		}
	}

	async function previewReport(postId, reportId, host) {
		const preview = element('section', 'iaad-historical-preview');
		preview.appendChild(element('h4', '', 'نسخهٔ گزارش #' + reportId));
		preview.appendChild(element('p', 'iaad-loading-text', config.i18n?.loading || 'در حال بارگذاری…'));
		host.insertBefore(preview, host.querySelector('.iaad-history-section'));
		try {
			const result = await request('posts/' + encodeURIComponent(postId) + '/report/' + encodeURIComponent(reportId));
			const report = result.data || {};
			preview.replaceChildren(element('h4', '', (report.post_title || 'گزارش') + ' · #' + report.report_id));
			const overall = report.overall || {};
			preview.appendChild(element('p', '', 'امتیاز: ' + formatNumber(overall.score) + ' · وضعیت: ' + (overall.status || '—') + ' · پوشش: ' + formatNumber(overall.coverage) + '%'));
			preview.appendChild(element('p', 'iaad-report-meta', 'قواعد: ' + (report.rules_version || '—') + ' · تاریخ: ' + (report.audited_at ? new Date(report.audited_at).toLocaleString('fa-IR') : '—')));
			const issues = Array.isArray(report.issues) ? report.issues : [];
			issues.slice(0, 10).forEach(function (issue) { preview.appendChild(element('p', '', issue.rule_id + ': ' + (issue.message || ''))); });
		} catch (error) {
			preview.replaceChildren(element('h4', '', 'دریافت نسخهٔ گزارش ناموفق بود'), showMessage(preview, error.message || config.i18n?.error || 'گزارش در دسترس نیست.', 'error'));
		}
	}

	async function renderClaims(content) {
		content.appendChild(element('h2', '', 'ادعاهای نیازمند بررسی انسانی'));
		const description = element('p', '', 'تأیید یا رد در این بخش فقط وضعیت ادعای ذخیره‌شده در موتور را تغییر می‌دهد؛ محتوای نوشته ویرایش نمی‌شود.');
		content.appendChild(description);
		const host = element('div', 'iaad-claims-host'); content.appendChild(host);
		host.appendChild(element('p', 'iaad-loading-text', config.i18n?.loading || 'در حال بارگذاری…'));
		try {
			const result = await request('claims', { params: { review_state: 'pending', per_page: 50 } });
			if (!content.isConnected) return;
			const rows = Array.isArray(result.data) ? result.data : [];
			host.replaceChildren();
			if (!rows.length) { host.appendChild(element('p', '', config.i18n?.noData || 'ادعای در انتظار بررسی وجود ندارد.')); return; }
			const table = element('table', 'widefat striped iaad-table');
			const header = element('tr', ''); ['نوشته', 'نوع ادعا', 'مقدار', 'بافت', 'وضعیت', 'عملیات'].forEach(function (label) { header.appendChild(element('th', '', label)); }); table.appendChild(header);
			rows.forEach(function (claim) {
				const tr = element('tr', ''); cell(tr, '#' + claim.post_id); cell(tr, claim.kind); cell(tr, claim.claim_value);
				const context = claim.context && typeof claim.context === 'object' ? JSON.stringify(claim.context) : '';
				cell(tr, context);
				const stateCell = element('td', ''); stateCell.appendChild(statusBadge(claim.review_state)); tr.appendChild(stateCell);
				const action = element('td', '');
				action.append(button('تأیید انسانی', 'button button-small', function () { updateClaim(claim.id, 'verified_by_human', content); }));
				action.append(button('رد', 'button button-small iaad-danger-button', function () { updateClaim(claim.id, 'rejected', content); }));
				tr.appendChild(action); table.appendChild(tr);
			});
			host.appendChild(table);
		} catch (error) { host.replaceChildren(); showMessage(host, error.message || config.i18n?.error || 'بارگذاری ادعاها ناموفق بود.', 'error'); }
	}

	async function updateClaim(id, reviewState, content) {
		if (!window.confirm(config.i18n?.confirmClaim || 'وضعیت ادعا تغییر کند؟')) return;
		try {
			await request('claims/' + encodeURIComponent(id), { method: 'PATCH', body: { review_state: reviewState } });
			renderClaims(content);
		} catch (error) { alert(error.message); }
	}

	async function renderRules(content) {
		content.appendChild(element('h2', '', 'فهرست قواعد ممیزی'));
		content.appendChild(element('p', '', 'این بخش فقط داده‌های قراردادی Engine را نمایش می‌دهد؛ هیچ قاعده‌ای در افزونهٔ Dashboard اجرا نمی‌شود.'));
		const host = element('div', 'iaad-rules-host'); content.appendChild(host);
		host.appendChild(element('p', 'iaad-loading-text', config.i18n?.loading || 'در حال بارگذاری…'));
		try {
			const result = await request('rules');
			if (!content.isConnected) return;
			const rules = Array.isArray(result.data?.rules) ? result.data.rules : [];
			host.replaceChildren();
			if (!rules.length) { host.appendChild(element('p', '', config.i18n?.noData || 'قانونی برای نمایش موجود نیست.')); return; }
			const table = element('table', 'widefat striped iaad-table');
			const header = element('tr', ''); ['شناسه', 'عنوان', 'دسته', 'شدت', 'وزن', 'مبنای ارزیابی', 'نیازمند بررسی انسانی'].forEach(function (label) { header.appendChild(element('th', '', label)); }); table.appendChild(header);
			rules.forEach(function (rule) { const tr = element('tr', ''); cell(tr, rule.id); cell(tr, rule.title); cell(tr, rule.category); cell(tr, rule.severity); cell(tr, formatNumber(rule.weight)); cell(tr, rule.basis); cell(tr, rule.manual_review_only ? 'بله' : 'خیر'); table.appendChild(tr); });
			host.appendChild(table);
		} catch (error) { host.replaceChildren(); showMessage(host, error.message || config.i18n?.error || 'بارگذاری قوانین ناموفق بود.', 'error'); }
	}

	async function downloadCsv() {
		try {
			const result = await request('export', { params: { format: 'csv' }, responseType: 'blob' });
			const blob = result.data;
			const url = URL.createObjectURL(blob);
			const link = element('a', '', ''); link.href = url; link.download = 'iran-audit-export.csv'; link.hidden = true;
			document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
		} catch (error) { alert(error.message); }
	}

	async function boot() {
		try {
			const result = await request('status');
			state.status = result.data || {};
			if (!state.status.api_version) throw new Error(config.i18n?.engineMissing || 'موتور ممیزی فعال نیست.');
			const match = String(state.status.api_version).match(/^(\d+)\.(\d+)$/);
			if (!match || Number(match[1]) !== 1) {
				app.replaceChildren(showMessage(app, 'نسخهٔ اصلی API موتور با این داشبورد سازگار نیست؛ برای جلوگیری از نمایش نادرست داده، داشبورد متوقف شد.', 'error'));
				return;
			}
			if (Number(match[2]) < 1) {
				app.replaceChildren(showMessage(app, 'نسخهٔ API موتور قدیمی‌تر از حداقل موردنیاز 1.1 است؛ Engine را به‌روزرسانی کنید.', 'error'));
				return;
			}
			startShell();
		} catch (error) {
			app.replaceChildren();
			const missing = element('section', 'iaad-engine-missing');
			missing.appendChild(element('h2', '', error.status === 403 ? 'مجوز مشاهدهٔ ممیزی ندارید' : 'موتور ممیزی در دسترس نیست'));
			const message = error.status === 404
				? (config.i18n?.engineMissing || 'ابتدا افزونهٔ Iran Audit Engine را نصب و فعال کنید.')
				: error.status === 403
					? 'برای دیدن گزارش‌ها، مدیر سایت باید capability «iaa_view» را به نقش شما بدهد.'
					: (error.message || config.i18n?.engineMissing || 'ابتدا افزونهٔ Iran Audit Engine را نصب و فعال کنید.');
			missing.appendChild(showMessage(missing, message, 'error'));
			missing.appendChild(element('p', '', 'پس از فعال‌سازی Engine یا اصلاح مجوز، صفحه را دوباره بارگذاری کنید. API موردنیاز داشبورد نسخهٔ ۱٫۱ است.'));
			app.appendChild(missing);
		}
	}

	boot();
})();
