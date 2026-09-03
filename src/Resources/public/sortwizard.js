/*
 * Sortier-Assistenten der Fotoalben.
 *
 * Ersetzt die frueher benutzten MooTools-"Sortables": Contao 5 liefert
 * MooTools im Backend nicht mehr aus, und die alte Loesung schrieb bei jedem
 * Klick auf "hoch"/"runter" sofort in die Datenbank. Hier wird nur im Browser
 * umsortiert; gespeichert wird beim Absenden des Formulars, weil die
 * versteckten Eingabefelder mit den Eintraegen mitwandern.
 *
 * Bedienung: mit der Maus ziehen oder den Eintrag anklicken und mit
 * Strg+Pfeiltaste verschieben.
 *
 * @license LGPL-3.0-or-later
 */
(function () {
	'use strict';

	/**
	 * Haengt die Ereignisse an eine Liste.
	 *
	 * @param {HTMLElement} container Das Element mit data-pa2-sortwizard
	 */
	function init(container) {
		var list = container.querySelector('.sortable');

		if (!list || list.getAttribute('data-pa2-ready')) {
			return;
		}

		list.setAttribute('data-pa2-ready', '1');

		// Bilder liegen nebeneinander, Auswahllisten untereinander
		var horizontal = container.getAttribute('data-pa2-sortwizard') === 'images';
		var dragged = null;

		Array.prototype.forEach.call(list.querySelectorAll('.pa2-sortitem'), function (item) {
			if (!item.hasAttribute('tabindex')) {
				item.setAttribute('tabindex', '0');
			}
		});

		list.addEventListener('dragstart', function (event) {
			var item = event.target.closest ? event.target.closest('.pa2-sortitem') : null;

			if (!item) {
				return;
			}

			dragged = item;
			item.classList.add('pa2-dragging');

			if (event.dataTransfer) {
				event.dataTransfer.effectAllowed = 'move';

				// Firefox startet das Ziehen nur mit gesetzten Daten
				try {
					event.dataTransfer.setData('text/plain', '');
				} catch (e) {
					// Aeltere Browser kennen nur "Text"
					try {
						event.dataTransfer.setData('Text', '');
					} catch (e2) {
						// Ohne Daten laesst sich hier nichts mehr retten
					}
				}
			}
		});

		list.addEventListener('dragend', function () {
			if (dragged) {
				dragged.classList.remove('pa2-dragging');
			}

			dragged = null;
		});

		list.addEventListener('dragover', function (event) {
			if (!dragged) {
				return;
			}

			event.preventDefault();

			if (event.dataTransfer) {
				event.dataTransfer.dropEffect = 'move';
			}

			var target = event.target.closest ? event.target.closest('.pa2-sortitem') : null;

			if (!target || target === dragged || target.parentNode !== list) {
				return;
			}

			var rect = target.getBoundingClientRect();
			var after = horizontal
				? (event.clientX - rect.left) > rect.width / 2
				: (event.clientY - rect.top) > rect.height / 2;

			list.insertBefore(dragged, after ? target.nextSibling : target);
		});

		list.addEventListener('drop', function (event) {
			event.preventDefault();
		});

		list.addEventListener('keydown', function (event) {
			if (!event.ctrlKey && !event.metaKey) {
				return;
			}

			var item = event.target.closest ? event.target.closest('.pa2-sortitem') : null;

			if (!item) {
				return;
			}

			var back = event.key === 'ArrowLeft' || event.key === 'ArrowUp';
			var forward = event.key === 'ArrowRight' || event.key === 'ArrowDown';

			if (!back && !forward) {
				return;
			}

			event.preventDefault();

			if (back && item.previousElementSibling) {
				list.insertBefore(item, item.previousElementSibling);
			} else if (forward && item.nextElementSibling) {
				list.insertBefore(item.nextElementSibling, item);
			}

			item.focus();
		});
	}

	/**
	 * Sucht alle Assistenten auf der Seite und richtet sie ein.
	 */
	function initAll() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-pa2-sortwizard]'), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAll);
	} else {
		initAll();
	}
})();
