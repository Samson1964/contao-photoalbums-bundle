/*
 * Video-Ueberlagerer der Fotoalben.
 *
 * Die Lightbox eines Themes bekommt ihre Einstellungen einmal fuer alle
 * Verweise mit data-lightbox; colorbox etwa erkennt am Dateinamen nur Bilder
 * und wuerde ein Video zu laden versuchen. Videos tragen deshalb
 * data-pa2-video, und dieses Skript legt sie in einen eigenen Ueberlagerer.
 *
 * Es kommt ohne Bibliothek aus und laeuft damit unabhaengig davon, ob das
 * Theme jQuery, MooTools oder gar nichts einbindet.
 *
 * Bedienung: Anklicken oeffnet, Escape oder ein Klick auf den Hintergrund
 * schliesst. Beim Schliessen haelt das Video an und springt an den Anfang.
 *
 * @license LGPL-3.0-or-later
 */
(function () {
	'use strict';

	var overlay = null;
	var video = null;
	var letzterAnfasser = null;

	/**
	 * Baut den Ueberlagerer beim ersten Bedarf.
	 *
	 * @returns {HTMLElement} Das Wurzelelement des Ueberlagerers
	 */
	function erzeugen() {
		if (overlay) {
			return overlay;
		}

		overlay = document.createElement('div');
		overlay.className = 'pa2-videobox';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.hidden = true;

		var rahmen = document.createElement('div');
		rahmen.className = 'pa2-videobox-inner';

		video = document.createElement('video');
		video.setAttribute('controls', 'controls');
		video.setAttribute('playsinline', 'playsinline');
		video.setAttribute('preload', 'metadata');

		var schliessen = document.createElement('button');
		schliessen.type = 'button';
		schliessen.className = 'pa2-videobox-close';
		schliessen.innerHTML = '&times;';

		rahmen.appendChild(schliessen);
		rahmen.appendChild(video);
		overlay.appendChild(rahmen);
		document.body.appendChild(overlay);

		schliessen.addEventListener('click', zu);

		// Nur der Hintergrund schliesst, nicht ein Klick auf das Video selbst
		overlay.addEventListener('click', function (event) {
			if (event.target === overlay) {
				zu();
			}
		});

		return overlay;
	}

	/**
	 * Oeffnet den Ueberlagerer mit einer Videodatei.
	 *
	 * @param {string} quelle Die Adresse der Videodatei
	 * @param {string} typ    Der MIME-Typ, darf leer sein
	 * @param {string} titel  Beschriftung fuer Vorlesewerkzeuge
	 */
	function auf(quelle, typ, titel) {
		erzeugen();

		while (video.firstChild) {
			video.removeChild(video.firstChild);
		}

		var quelleEl = document.createElement('source');
		quelleEl.src = quelle;

		if (typ) {
			quelleEl.type = typ;
		}

		video.appendChild(quelleEl);
		video.setAttribute('aria-label', titel || '');
		overlay.setAttribute('aria-label', titel || '');
		overlay.hidden = false;
		document.documentElement.classList.add('pa2-videobox-offen');

		video.load();

		// Ein abgewiesenes Abspielen ist kein Fehler: Manche Browser verlangen
		// dafuer eine ausdrueckliche Geste. Die Bedienleiste steht ja bereit.
		var versuch = video.play();

		if (versuch && typeof versuch.catch === 'function') {
			versuch.catch(function () {});
		}

		video.focus({ preventScroll: true });
	}

	/**
	 * Schliesst den Ueberlagerer und haelt das Video an.
	 */
	function zu() {
		if (!overlay || overlay.hidden) {
			return;
		}

		video.pause();
		video.removeAttribute('src');

		while (video.firstChild) {
			video.removeChild(video.firstChild);
		}

		video.load();

		overlay.hidden = true;
		document.documentElement.classList.remove('pa2-videobox-offen');

		if (letzterAnfasser) {
			letzterAnfasser.focus();
			letzterAnfasser = null;
		}
	}

	document.addEventListener('click', function (event) {
		var anfasser = event.target.closest ? event.target.closest('[data-pa2-video]') : null;

		if (!anfasser) {
			return;
		}

		event.preventDefault();
		letzterAnfasser = anfasser;

		auf(
			anfasser.getAttribute('data-pa2-video'),
			anfasser.getAttribute('data-pa2-video-type') || '',
			anfasser.getAttribute('title') || ''
		);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			zu();
		}
	});
})();
