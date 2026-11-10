/* ممیزی مقالات زنده: فیلتر سمت مرورگر روی جدول تطبیق (بدون هیچ درخواست شبکه). */
(function () {
	'use strict';
	var search = document.getElementById('sa-laa-search');
	var status = document.getElementById('sa-laa-status');
	var table = document.getElementById('sa-laa-matched');
	if (!search || !status || !table) {
		return;
	}
	function apply() {
		var q = (search.value || '').trim().toLowerCase();
		var s = status.value || '';
		var rows = table.querySelectorAll('tbody tr');
		rows.forEach(function (row) {
			var okStatus = !s || row.getAttribute('data-status') === s;
			var okSearch = !q || (row.getAttribute('data-search') || '').toLowerCase().indexOf(q) !== -1;
			row.style.display = okStatus && okSearch ? '' : 'none';
		});
	}
	search.addEventListener('input', apply);
	status.addEventListener('change', apply);
})();
