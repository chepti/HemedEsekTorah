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
		initCarousels();
		initChecklist();
		initLikes();
	});
})();
