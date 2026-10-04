// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Jupyter notebook (.ipynb) viewer: clicking a notebook link (a file resource, an assignment's attached files, a
 * student's submission or feedback file) opens the notebook in a modal instead of downloading it.
 *
 * The file is fetched with the user's session, so Moodle's normal file access rules apply. Code cells are
 * highlighted here (escaped, then wrapped in spans); markdown cells and HTML outputs (e.g. pandas tables) are
 * converted and cleaned on the server (theme_iiitdwd_sanitise_notebook_html, Moodle's HTML Purifier), because
 * notebooks are untrusted. Images (plots) are shown from their embedded base64 data. Ctrl/Cmd/Shift/middle click
 * keep the browser's default (download, new tab), and the viewer has a "Download original .ipynb" button.
 *
 * Written as plain AMD so the same file works as src and build without grunt.
 *
 * @module     theme_iiitdwd/ipynb_viewer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/modal', 'core/ajax', 'core/notification'], function(ModalModule, Ajax, Notification) {
    'use strict';

    var Modal = ModalModule.default || ModalModule;

    /** Notebooks larger than this are not rendered (offered for download instead). */
    var MAX_BYTES = 20 * 1024 * 1024;

    /** Output MIME types, best first. */
    var IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/svg+xml'];

    var PY_KEYWORDS = ('False None True and as assert async await break class continue def del elif else except ' +
        'finally for from global if import in is lambda nonlocal not or pass raise return try while with yield match case')
        .split(' ');
    var PY_BUILTINS = ('print len range int float str list dict set tuple bool type isinstance enumerate zip map filter ' +
        'sorted sum min max abs round open input super object Exception ValueError TypeError KeyError IndexError self')
        .split(' ');

    var config = null;

    // ---- Helpers ------------------------------------------------------------------------------------------------

    var escapeHtml = function(text) {
        return String(text).replace(/[&<>"']/g, function(c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    };

    /** Notebook fields are a string or a list of lines. */
    var joinSource = function(source) {
        return Array.isArray(source) ? source.join('') : String(source || '');
    };

    /** Tracebacks carry terminal colour codes. */
    var stripAnsi = function(text) {
        // eslint-disable-next-line no-control-regex
        return text.replace(/\x1b\[[0-9;]*[A-Za-z]/g, '');
    };

    var str = function(key, a) {
        var s = config.strings[key] || key;
        return a === undefined ? s : s.replace('{$a}', a);
    };

    /** Python syntax highlighting: every piece is escaped, tokens are wrapped in spans. */
    var highlightPython = function(code) {
        var pattern = new RegExp([
            '(#[^\\n]*)', // Comment.
            '([rRbBuUfF]{0,2}(?:"""[\\s\\S]*?"""|\'\'\'[\\s\\S]*?\'\'\'|"(?:\\\\.|[^"\\\\\\n])*"|\'(?:\\\\.|[^\'\\\\\\n])*\'))', // String.
            '(\\b\\d[\\d_]*(?:\\.\\d+)?(?:[eE][+-]?\\d+)?j?\\b)', // Number.
            '(@[A-Za-z_][\\w.]*)', // Decorator.
            '(\\b[A-Za-z_]\\w*\\b)', // Name.
        ].join('|'), 'g');
        var out = '';
        var last = 0;
        var previous = '';
        code.replace(pattern, function(match, comment, string, number, decorator, name, offset) {
            out += escapeHtml(code.slice(last, offset));
            last = offset + match.length;
            var cls = null;
            if (comment) {
                cls = 'c';
            } else if (string) {
                cls = 's';
            } else if (number) {
                cls = 'm';
            } else if (decorator) {
                cls = 'd';
            } else if (PY_KEYWORDS.indexOf(name) !== -1) {
                cls = 'k';
            } else if (previous === 'def' || previous === 'class') {
                cls = 'f';
            } else if (PY_BUILTINS.indexOf(name) !== -1) {
                cls = 'b';
            }
            previous = name || '';
            out += cls ? '<span class="tok-' + cls + '">' + escapeHtml(match) + '</span>' : escapeHtml(match);
            return match;
        });
        return out + escapeHtml(code.slice(last));
    };

    // ---- Rendering ----------------------------------------------------------------------------------------------

    /**
     * Builds the notebook HTML. Markdown and HTML fragments become placeholders, filled after the server cleans them.
     *
     * @param {Object} nb Parsed notebook.
     * @param {Array} pending Collects {format, text} to clean; the placeholder index is the array index.
     * @return {string}
     */
    var renderNotebook = function(nb, pending) {
        var cells = nb.cells || (nb.worksheets && nb.worksheets[0] && nb.worksheets[0].cells) || [];
        var meta = nb.metadata || {};
        var language = ((meta.kernelspec && meta.kernelspec.language) ||
            (meta.language_info && meta.language_info.name) || 'python').toLowerCase();

        var placeholder = function(format, text) {
            pending.push({format: format, text: text});
            return '<div class="ipynb-rich" data-ipynb-fragment="' + (pending.length - 1) + '"></div>';
        };

        var renderOutput = function(output) {
            var type = output.output_type;
            if (type === 'stream') {
                return '<pre class="ipynb-output ipynb-stream' + (output.name === 'stderr' ? ' ipynb-stderr' : '') + '">' +
                    escapeHtml(stripAnsi(joinSource(output.text))) + '</pre>';
            }
            if (type === 'error' || type === 'pyerr') {
                var trace = (output.traceback || []).map(stripAnsi).join('\n') ||
                    (output.ename + ': ' + output.evalue);
                return '<pre class="ipynb-output ipynb-error">' + escapeHtml(trace) + '</pre>';
            }
            var data = output.data || {};
            for (var i = 0; i < IMAGE_TYPES.length; i++) {
                var mime = IMAGE_TYPES[i];
                if (data[mime]) {
                    var value = joinSource(data[mime]);
                    var base64 = mime === 'image/svg+xml'
                        // SVG is stored as text; as an <img> it cannot run scripts.
                        ? window.btoa(unescape(encodeURIComponent(value)))
                        : value.replace(/\s/g, '');
                    if (!/^[A-Za-z0-9+/=]+$/.test(base64)) {
                        continue;
                    }
                    return '<div class="ipynb-output ipynb-image"><img src="data:' + mime + ';base64,' + base64 +
                        '" alt=""></div>';
                }
            }
            if (data['text/html']) {
                return '<div class="ipynb-output ipynb-html">' + placeholder('html', joinSource(data['text/html'])) + '</div>';
            }
            if (data['text/markdown']) {
                return '<div class="ipynb-output ipynb-html">' +
                    placeholder('markdown', joinSource(data['text/markdown'])) + '</div>';
            }
            var text = data['text/plain'] || output.text;
            if (text !== undefined) {
                return '<pre class="ipynb-output">' + escapeHtml(stripAnsi(joinSource(text))) + '</pre>';
            }
            return '';
        };

        var html = cells.map(function(cell) {
            var source = joinSource(cell.source !== undefined ? cell.source : cell.input);
            if (cell.cell_type === 'markdown') {
                // Images pasted into markdown cells are stored as attachments.
                Object.keys(cell.attachments || {}).forEach(function(name) {
                    var bundle = cell.attachments[name];
                    var mime = Object.keys(bundle)[0];
                    source = source.split('attachment:' + name).join('data:' + mime + ';base64,' + joinSource(bundle[mime]));
                });
                return '<div class="ipynb-cell ipynb-markdown">' + placeholder('markdown', source) + '</div>';
            }
            if (cell.cell_type === 'code') {
                var count = cell.execution_count !== undefined ? cell.execution_count : cell.prompt_number;
                var label = count === null || count === undefined ? ' ' : count;
                var code = language === 'python' ? highlightPython(source) : escapeHtml(source);
                var outputs = (cell.outputs || []).map(renderOutput).join('');
                return '<div class="ipynb-cell ipynb-code">' +
                    '<div class="ipynb-prompt" aria-hidden="true">' + escapeHtml(str('in', label)) + '</div>' +
                    '<pre class="ipynb-source"><code>' + code + '</code></pre>' +
                    (outputs ? '<div class="ipynb-outputs">' + outputs + '</div>' : '') +
                    '</div>';
            }
            return '<div class="ipynb-cell ipynb-raw"><pre>' + escapeHtml(source) + '</pre></div>';
        }).join('');

        return '<div class="ipynb-notebook">' + html + '</div>';
    };

    /**
     * Sends the markdown/HTML fragments to the server to be cleaned, then fills the placeholders.
     *
     * @param {HTMLElement} root
     * @param {Array} pending
     * @return {Promise}
     */
    var fillFragments = function(root, pending) {
        if (!pending.length) {
            return Promise.resolve();
        }
        return Ajax.call([{methodname: 'theme_iiitdwd_sanitise_notebook_html', args: {items: pending}}])[0]
            .then(function(htmls) {
                root.querySelectorAll('[data-ipynb-fragment]').forEach(function(el) {
                    el.innerHTML = htmls[+el.getAttribute('data-ipynb-fragment')] || '';
                });
                // Links in the notebook open outside the viewer.
                root.querySelectorAll('.ipynb-rich a[href]').forEach(function(a) {
                    a.target = '_blank';
                    a.rel = 'noopener';
                });
                return null;
            });
    };

    // ---- Viewer -------------------------------------------------------------------------------------------------

    var downloadUrl = function(url) {
        var u = new URL(url, window.location.href);
        u.searchParams.set('forcedownload', '1');
        return u.href;
    };

    /**
     * Opens a notebook in the viewer.
     *
     * @param {string} url The file (pluginfile.php) or resource (view.php?redirect=1) URL.
     * @param {string} filename Shown as the title.
     * @return {Promise}
     */
    var open = function(url, filename) {
        var notebookUrl = url;
        return Modal.create({
            title: filename,
            body: '<div class="ipynb-status">' + escapeHtml(str('loading')) + '</div>',
            large: true,
            show: true,
            removeOnClose: true,
        }).then(function(modal) {
            var root = modal.getRoot()[0];
            root.classList.add('iiitdwd-ipynb-modal');

            // "Download original .ipynb" in the header, before the close button.
            var download = document.createElement('a');
            download.className = 'btn btn-sm btn-outline-secondary iiitdwd-ipynb-download';
            download.textContent = str('download');
            download.href = downloadUrl(url);
            var header = root.querySelector('.modal-header');
            header.insertBefore(download, header.querySelector('.btn-close, .close'));

            return fetch(url, {credentials: 'same-origin'}).then(function(response) {
                if (!response.ok) {
                    throw new Error(str('error'));
                }
                // Resource links redirect to the file; download that.
                notebookUrl = response.url || url;
                download.href = downloadUrl(notebookUrl);
                if (+response.headers.get('Content-Length') > MAX_BYTES) {
                    throw new Error(str('toolarge'));
                }
                return response.text();
            }).then(function(text) {
                if (text.length > MAX_BYTES) {
                    throw new Error(str('toolarge'));
                }
                var nb;
                try {
                    nb = JSON.parse(text);
                } catch (e) {
                    throw new Error(str('error'));
                }
                var pending = [];
                var html = renderNotebook(nb, pending);
                var body = root.querySelector('.modal-body');
                body.innerHTML = html;
                return fillFragments(body, pending);
            }).catch(function(e) {
                var body = root.querySelector('.modal-body');
                body.innerHTML = '<div class="ipynb-status ipynb-status-error">' + escapeHtml(e.message || str('error')) +
                    '</div>';
                if (!(e instanceof Error)) {
                    Notification.exception(e);
                }
            });
        });
    };

    /**
     * The notebook behind a link, or null.
     *
     * @param {HTMLAnchorElement} link
     * @return {Object|null} {url, filename}
     */
    var notebookFor = function(link) {
        var u;
        try {
            u = new URL(link.href, window.location.href);
        } catch (e) {
            return null;
        }
        if (u.origin !== window.location.origin) {
            return null;
        }
        var path = decodeURIComponent(u.pathname);
        if (/\/(pluginfile|draftfile)\.php\//.test(path) && /\.ipynb$/i.test(path)) {
            return {url: u.href, filename: path.split('/').pop()};
        }
        // File resources whose main file is a notebook (cmids sent by the renderer).
        if (/\/mod\/resource\/view\.php$/.test(path) && config.resourcecmids.indexOf(+u.searchParams.get('id')) !== -1) {
            u.searchParams.set('redirect', '1');
            return {url: u.href, filename: (link.textContent || '').trim() || 'notebook.ipynb'};
        }
        return null;
    };

    return {
        /**
         * @param {Object} cfg
         * @param {number[]} cfg.resourcecmids File resources on this page whose main file is a notebook.
         * @param {Object} cfg.strings loading, error, toolarge, download, in
         */
        init: function(cfg) {
            if (config) {
                return;
            }
            config = cfg;
            // Capture phase, so the viewer opens before other handlers (e.g. resource pop-ups) act on the click.
            document.addEventListener('click', function(e) {
                if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) {
                    return;
                }
                var link = e.target.closest('a[href]');
                if (!link || link.hasAttribute('download') || link.closest('.iiitdwd-ipynb-modal')) {
                    return;
                }
                var notebook = notebookFor(link);
                if (!notebook) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                open(notebook.url, notebook.filename);
            }, true);
        },

        open: open,
    };
});
