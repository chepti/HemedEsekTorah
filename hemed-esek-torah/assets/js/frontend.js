(function () {
	'use strict';

	function ready(callback) {
		if (document.readyState !== 'loading') {
			callback();
			return;
		}
		document.addEventListener('DOMContentLoaded', callback);
	}

	function initModal() {
		var modal = document.querySelector('[data-het-modal]');
		var openers = document.querySelectorAll('[data-het-open-modal]');
		var closers = document.querySelectorAll('[data-het-close-modal]');

		if (!modal || !openers.length) {
			return;
		}

		function openModal() {
			modal.hidden = false;
			document.body.classList.add('het-modal-open');
			var firstInput = modal.querySelector('input, select, textarea, button');
			if (firstInput) {
				firstInput.focus();
			}
		}

		function closeModal() {
			modal.hidden = true;
			document.body.classList.remove('het-modal-open');
		}

		openers.forEach(function (button) {
			button.addEventListener('click', openModal);
		});

		closers.forEach(function (button) {
			button.addEventListener('click', closeModal);
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !modal.hidden) {
				closeModal();
			}
		});

		if (window.location.search.indexOf('het_message=') !== -1) {
			openModal();
		}
	}

	function initCarousels() {
		document.querySelectorAll('[data-het-carousel]').forEach(function (carousel) {
			var slides = Array.prototype.slice.call(carousel.querySelectorAll('.het-gallery__slide'));
			var previous = carousel.querySelector('[data-het-carousel-prev]');
			var next = carousel.querySelector('[data-het-carousel-next]');
			var current = 0;

			if (slides.length <= 1) {
				if (previous) {
					previous.hidden = true;
				}
				if (next) {
					next.hidden = true;
				}
				return;
			}

			function show(index) {
				current = (index + slides.length) % slides.length;
				slides.forEach(function (slide, slideIndex) {
					slide.classList.toggle('is-active', slideIndex === current);
				});
			}

			if (previous) {
				previous.addEventListener('click', function () {
					show(current - 1);
				});
			}

			if (next) {
				next.addEventListener('click', function () {
					show(current + 1);
				});
			}
		});
	}

	function initChecklist() {
		document.querySelectorAll('.het-checklist input[type="checkbox"]').forEach(function (checkbox) {
			checkbox.addEventListener('change', function () {
				checkbox.closest('li').classList.toggle('is-checked', checkbox.checked);
			});
		});
	}

	function updateFileRowDisplay(row) {
		var input = row.querySelector('input[type="file"]');
		var nameEl = row.querySelector('[data-het-file-name]');
		var clearBtn = row.querySelector('[data-het-clear-file]');
		if (!input || !nameEl) {
			return;
		}
		var f = input.files && input.files[0];
		if (f) {
			nameEl.textContent = f.name;
			nameEl.hidden = false;
			if (clearBtn) {
				clearBtn.hidden = false;
			}
		} else {
			nameEl.textContent = '';
			nameEl.hidden = true;
			if (clearBtn) {
				clearBtn.hidden = true;
			}
		}
	}

	function bindFileRow(row, afterChange) {
		var input = row.querySelector('input[type="file"]');
		if (!input) {
			return;
		}
		function refresh() {
			updateFileRowDisplay(row);
			if (typeof afterChange === 'function') {
				afterChange();
			}
		}
		input.addEventListener('change', refresh);
		var clearBtn = row.querySelector('[data-het-clear-file]');
		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				refresh();
			});
		}
		updateFileRowDisplay(row);
	}

	function initImageDynamicBlock(root) {
		var wrap = root.querySelector('[data-het-slots-wrap]');
		var addBtn = root.querySelector('[data-het-add-image]');
		var max = parseInt(root.getAttribute('data-het-max-slots'), 10) || 5;
		if (!wrap || !addBtn) {
			return;
		}

		function getSlots() {
			return wrap.querySelectorAll('.het-file-slot');
		}

		function syncImageAdd() {
			var slots = getSlots();
			var last = slots[slots.length - 1];
			var inp = last ? last.querySelector('input[type="file"]') : null;
			var has = inp && inp.files && inp.files.length;
			addBtn.hidden = !(has && slots.length < max);
		}

		wrap.querySelectorAll('.het-file-slot__row').forEach(function (r) {
			bindFileRow(r, syncImageAdd);
		});

		addBtn.addEventListener('click', function () {
			var slots = getSlots();
			var next = slots.length + 1;
			var id = 'het_image_' + next;
			var slot = document.createElement('div');
			slot.className = 'het-file-slot';
			slot.setAttribute('data-het-slot-index', String(next));
			slot.innerHTML =
				'<span class="het-file-slot__caption">תמונה ' +
				next +
				'</span>' +
				'<div class="het-file-slot__row">' +
				'<input class="het-visually-hidden" type="file" id="' +
				id +
				'" name="' +
				id +
				'" accept="image/*">' +
				'<label class="het-file-pill" for="' +
				id +
				'"><span class="het-file-pill__plus" aria-hidden="true">+</span><span class="het-visually-hidden">בחירת תמונה</span></label>' +
				'<span class="het-file-slot__name" data-het-file-name hidden></span>' +
				'<button type="button" class="het-file-slot__clear" data-het-clear-file hidden aria-label="ניקוי בחירת הקובץ">×</button>' +
				'</div>';
			wrap.appendChild(slot);
			bindFileRow(slot.querySelector('.het-file-slot__row'), syncImageAdd);
			addBtn.hidden = true;
			syncImageAdd();
		});

		syncImageAdd();
	}

	function initResourceDynamicBlock(root) {
		var wrap = root.querySelector('[data-het-slots-wrap]');
		var addBtn = root.querySelector('[data-het-add-resource]');
		var max = parseInt(root.getAttribute('data-het-max-slots'), 10) || 5;
		if (!wrap || !addBtn) {
			return;
		}

		function getRows() {
			return wrap.querySelectorAll('.het-resource-row');
		}

		function syncResourceAdd() {
			var rows = getRows();
			var last = rows[rows.length - 1];
			if (!last) {
				addBtn.hidden = true;
				return;
			}
			var fin = last.querySelector('input[type="file"]');
			var urlin = last.querySelector('input[type="url"]');
			var hasFile = fin && fin.files && fin.files.length;
			var hasUrl = urlin && urlin.value.trim().length > 0;
			addBtn.hidden = !((hasFile || hasUrl) && rows.length < max);
		}

		function bindResourceRow(row) {
			var fileRow = row.querySelector('.het-file-slot__row');
			if (fileRow) {
				bindFileRow(fileRow, syncResourceAdd);
			}
			var urlin = row.querySelector('input[type="url"]');
			if (urlin) {
				urlin.addEventListener('input', syncResourceAdd);
			}
		}

		getRows().forEach(bindResourceRow);

		addBtn.addEventListener('click', function () {
			var rows = getRows();
			var next = rows.length + 1;
			var fileId = 'het_resource_file_' + next;
			var row = document.createElement('div');
			row.className = 'het-resource-row';
			row.setAttribute('data-het-slot-index', String(next));
			row.innerHTML =
				'<div class="het-resource-row__file">' +
				'<span class="het-file-slot__caption">קובץ עזר ' +
				next +
				'</span>' +
				'<div class="het-file-slot__row">' +
				'<input class="het-visually-hidden" type="file" id="' +
				fileId +
				'" name="het_resource_file_' +
				next +
				'">' +
				'<label class="het-file-pill" for="' +
				fileId +
				'"><span class="het-file-pill__plus" aria-hidden="true">+</span><span class="het-visually-hidden">בחירת קובץ עזר</span></label>' +
				'<span class="het-file-slot__name" data-het-file-name hidden></span>' +
				'<button type="button" class="het-file-slot__clear" data-het-clear-file hidden aria-label="ניקוי בחירת הקובץ">×</button>' +
				'</div></div>' +
				'<label class="het-resource-row__url"><span>קישור עזר ' +
				next +
				'</span><input type="url" name="het_resource_url_' +
				next +
				'" placeholder="https://"></label>';
			wrap.appendChild(row);
			bindResourceRow(row);
			addBtn.hidden = true;
			syncResourceAdd();
		});

		syncResourceAdd();
	}

	function initDynamicUploads() {
		document.querySelectorAll('[data-het-dynamic="images"]').forEach(initImageDynamicBlock);
		document.querySelectorAll('[data-het-dynamic="resources"]').forEach(initResourceDynamicBlock);
	}

	function initLikes() {
		document.querySelectorAll('[data-het-like]').forEach(function (button) {
			var postId = button.getAttribute('data-het-like');
			var storageKey = 'het-liked-' + postId;

			if (window.localStorage && localStorage.getItem(storageKey)) {
				button.classList.add('is-liked');
				button.disabled = true;
			}

			button.addEventListener('click', function () {
				if (button.disabled || !window.HemedEsekTorah) {
					return;
				}

				var body = new FormData();
				body.append('action', 'het_like_activity');
				body.append('nonce', window.HemedEsekTorah.nonce);
				body.append('postId', postId);

				button.disabled = true;

				fetch(window.HemedEsekTorah.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (payload) {
						if (!payload || !payload.success) {
							button.disabled = false;
							return;
						}

						var count = button.querySelector('[data-het-like-count]');
						if (count) {
							count.textContent = payload.data.likes;
						}
						button.classList.add('is-liked');

						if (window.localStorage) {
							localStorage.setItem(storageKey, '1');
						}
					})
					.catch(function () {
						button.disabled = false;
					});
			});
		});
	}

	ready(function () {
		initModal();
		initDynamicUploads();
		initCarousels();
		initChecklist();
		initLikes();
	});
})();
