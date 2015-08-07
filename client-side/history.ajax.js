(function($, undefined) {

// Is History API reliably supported? (based on Modernizr & PJAX)
if (!(window.history && history.pushState && window.history.replaceState && !navigator.userAgent.match(/((iPod|iPhone|iPad).+\bOS\s+[1-4]|WebApps\/.+CFNetwork)/))) return;

$.nette.ext('redirect', false);

var blockPopstateEvent = document.readyState !== 'complete';
var uiStates = [];
var currentStateId = -1;

$.nette.ext('history', {
	init: function () {
		var self = this, initialState;

		this.snippetsExt = $.nette.ext('snippets');

		initialState = this.createState(window.location.href, document.title, {});
		this.beforePushStateQueue.fire(initialState, null);
		history.replaceState(initialState, initialState.title, initialState.href);

		$(window).on('popstate.nette', $.proxy(function (e) {
			if (blockPopstateEvent && document.readyState === 'complete') {
				return;
			}

			var state = e.originalEvent.state;
			if (!state) {
				return;
			}

			this.saveSnippets();
			currentStateId = state.id;

			var uiState = uiStates[state.id]
			if (uiState) {
				this.updateTitle(state.title);
				this.updateSnippets(uiState.snippets)
			} else {
				$.nette.ajax({url: state.href, off: ['history']});
			}

			this.afterPopStateQueue.fire(state, uiState);
		}, this));

		setTimeout(function () { blockPopstateEvent = false; }, 0);
	},
	before: function (xhr, settings) {
		if (!settings.nette || (this.off && !settings.nette.el.is('[data-history-on]'))) {
			this.href = null;
		} else if (!settings.nette.form && !settings.nette.ui.href) {
			this.href = settings.url;
		} else if (!settings.nette.form) {
			this.href = settings.nette.ui.href;
		} else if (settings.nette.form.get(0).method === 'get') {
			this.href = settings.nette.form.get(0).action || window.location.href;
		} else {
			this.href = null;
		}

		if (this.href) {
			this.saveSnippets();
			xhr.setRequestHeader('X-History-Request', 'true');
		}
	},
	success: function (payload, status, xhr, settings) {
		var redirect = payload.redirect || payload.url; // backwards compatibility for 'url'
		if (redirect) {
			var regexp = new RegExp('//' + window.location.host + '($|/)');
			if (this.href && ((redirect.substring(0,4) === 'http') ? regexp.test(redirect) : true)) {
				this.href = redirect;
			} else {
				window.location.href = redirect;
			}
		}
		if (this.href && this.href != window.location.href) {
			this.pushState(this.href, document.title, {}, settings.nette && settings.nette.el);
		}
		this.href = null;
	}
}, {
	href: null,
	off: false,
	snippetsExt: null,

	beforeSaveStateQueue: $.Callbacks(),
	beforePushStateQueue: $.Callbacks(),
	afterPopStateQueue: $.Callbacks(),

	beforeSaveState: function (callback) {
		this.beforeSaveStateQueue.add(callback);
	},
	beforePushState: function (callback) {
		this.beforePushStateQueue.add(callback);
	},
	afterPopState: function (callback) {
		this.afterPopStateQueue.add(callback);
	},
	updateTitle: function (title) {
		document.title = title;
	},
	updateSnippets: function (snippets) {
		var updatedSnippets = {};
		$.each(snippets, function () {
			var html;
			if (this.excludedIds) {
				var $html = $('<div>').html(this.html);
				this.excludedIds.forEach(function (id) {
					$html.find('#' + id).html($('#' + id).html());
				});
				html = $html.html();
			} else {
				html = this.html;
			}
			updatedSnippets[this.id] = html;
		});
		this.snippetsExt.updateSnippets(updatedSnippets, true);
		$.nette.load();
	},
	createState: function (href, title, newSnippets) {
		currentStateId++;
		uiStates[currentStateId] = {newSnippets: newSnippets};
		return {
			id: currentStateId,
			nette: true,
			href: href,
			title: title,
		};
	},
	pushState: function (href, title, newSnippets, sender) {
		var state = this.createState(href, title, newSnippets);
		this.beforePushStateQueue.fire(state, sender);
		history.pushState(state, title, href);
		currentStateId = state.id;
	},
	saveSnippets: function () {
		var state = uiStates[currentStateId];
		state.snippets = this.extractSnippets(state.newSnippets);
		this.beforeSaveStateQueue.fire(state);
	},
	extractSnippets: function (newSnippets) {
		var snippets = {};

		function createSnippet(id) {
			if (id in snippets) {
				return snippets[id];
			} else {
				var snippet = {id: id, html: null, excludedIds: []};
				snippets[id] = snippet;
				return snippet;
			}
		}

		$('[id^="snippet-"]').each(function () {
			var $el = $(this), id = $el.attr('id'), cache = !$el.is('[data-history-nocache]'), parents = $el.parents('[id^="snippet-"]');

			if (cache && parents.length === 0) {
				createSnippet(id).html = newSnippets.hasOwnProperty(id) ? newSnippets[id] : $el.html();
			} else if (!cache && parents.length > 0) {
				createSnippet(parents.last().attr('id')).excludedIds.push(id);
			}
		});

		return snippets;
	}
});

})(jQuery);
