(function () {
	document.addEventListener('submit', function (e) {
		var form = e.target.closest('form[data-confirm]');
		if (form && !window.confirm(form.getAttribute('data-confirm'))) {
			e.preventDefault();
		}
	});
	document.addEventListener('change', function (e) {
		var master = e.target.closest('[data-select-all]');
		if (!master) return;
		var name = master.getAttribute('data-select-all');
		document.querySelectorAll('input[type="checkbox"][name="' + name + '"]').forEach(function (box) {
			box.checked = master.checked;
		});
	});
})();
