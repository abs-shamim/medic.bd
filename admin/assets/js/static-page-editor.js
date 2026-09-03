/*!
 * Static Page Rich Editor
 * Self-hosted, dependency-free editor for the static page form.
 *
 * This is intentionally a lightweight contenteditable editor. It saves HTML
 * back into the original hidden textarea before every form submission.
 */
(function () {
  'use strict';

  var ALLOWED_TAGS = {
    P: true, BR: true, H1: true, H2: true, H3: true, H4: true,
    STRONG: true, B: true, EM: true, I: true, U: true, S: true, STRIKE: true,
    SUP: true, SUB: true, UL: true, OL: true, LI: true, BLOCKQUOTE: true,
    PRE: true, CODE: true, A: true, IMG: true, TABLE: true, THEAD: true,
    TBODY: true, TR: true, TH: true, TD: true, HR: true, DIV: true, SECTION: true
  };

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function isSafeUrl(value, isImage) {
    var url = String(value || '').trim();

    if (url === '') {
      return false;
    }

    if (/^(https?:|mailto:|tel:|#|\/|\.\/|\.\.\/)/i.test(url)) {
      return isImage ? /^(https?:|\/|\.\/|\.\.\/)/i.test(url) : true;
    }

    return false;
  }

  function sanitizeHtml(markup) {
    var parser = new DOMParser();
    var documentNode = parser.parseFromString(String(markup || ''), 'text/html');
    var elements = Array.prototype.slice.call(documentNode.body.querySelectorAll('*'));

    elements.reverse().forEach(function (element) {
      var tag = element.tagName;

      if (!ALLOWED_TAGS[tag]) {
        var fragment = documentNode.createDocumentFragment();

        while (element.firstChild) {
          fragment.appendChild(element.firstChild);
        }

        element.replaceWith(fragment);
        return;
      }

      Array.prototype.slice.call(element.attributes).forEach(function (attribute) {
        var name = attribute.name.toLowerCase();
        var value = attribute.value;

        if (name.indexOf('on') === 0 || name === 'style' || name === 'id') {
          element.removeAttribute(attribute.name);
          return;
        }

        if (tag === 'A' && (name === 'href' || name === 'target' || name === 'rel' || name === 'title')) {
          if (name === 'href' && !isSafeUrl(value, false)) {
            element.removeAttribute(attribute.name);
          }

          if (name === 'target' && value !== '_blank') {
            element.removeAttribute(attribute.name);
          }

          return;
        }

        if (tag === 'IMG' && (name === 'src' || name === 'alt' || name === 'title' || name === 'width' || name === 'height' || name === 'loading')) {
          if (name === 'src' && !isSafeUrl(value, true)) {
            element.removeAttribute(attribute.name);
          }

          if ((name === 'width' || name === 'height') && !/^\d{1,4}$/.test(value)) {
            element.removeAttribute(attribute.name);
          }

          if (name === 'loading' && value !== 'lazy' && value !== 'eager') {
            element.removeAttribute(attribute.name);
          }

          return;
        }

        if ((tag === 'SECTION' || tag === 'DIV') && name === 'class') {
          var classNames = value.split(/\s+/).filter(function (className) {
            return className === 'medic-static-card' || className === 'medic-static-note';
          });

          if (classNames.length) {
            element.setAttribute('class', classNames.join(' '));
          } else {
            element.removeAttribute('class');
          }

          return;
        }

        element.removeAttribute(attribute.name);
      });

      if (tag === 'A' && element.getAttribute('target') === '_blank') {
        element.setAttribute('rel', 'noopener noreferrer');
      }

      if (tag === 'IMG' && element.getAttribute('src')) {
        element.setAttribute('loading', 'lazy');
      }
    });

    return documentNode.body.innerHTML;
  }

  function getTextWordCount(markup) {
    var container = document.createElement('div');
    container.innerHTML = markup;
    var text = (container.textContent || container.innerText || '').trim();

    if (!text) {
      return 0;
    }

    return text.split(/\s+/).filter(Boolean).length;
  }

  function closestForm(element) {
    while (element && element.tagName !== 'FORM') {
      element = element.parentElement;
    }

    return element;
  }

  function createButton(label, action, title, className) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'spe-button' + (className ? ' ' + className : '');
    button.setAttribute('data-spe-action', action);
    button.setAttribute('title', title || label);
    button.setAttribute('aria-label', title || label);
    button.textContent = label;

    return button;
  }

  function createSelect(action, label, options) {
    var select = document.createElement('select');
    select.className = 'spe-select';
    select.setAttribute('data-spe-select', action);
    select.setAttribute('aria-label', label);

    options.forEach(function (option) {
      var optionElement = document.createElement('option');
      optionElement.value = option.value;
      optionElement.textContent = option.label;
      select.appendChild(optionElement);
    });

    return select;
  }

  function createToolbar() {
    var toolbar = document.createElement('div');
    toolbar.className = 'spe-toolbar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', 'Page content formatting tools');

    var blockGroup = document.createElement('div');
    blockGroup.className = 'spe-toolbar-group';
    blockGroup.appendChild(createSelect('formatBlock', 'Text style', [
      { value: 'p', label: 'Normal' },
      { value: 'h1', label: 'Heading 1' },
      { value: 'h2', label: 'Heading 2' },
      { value: 'h3', label: 'Heading 3' },
      { value: 'h4', label: 'Heading 4' },
      { value: 'blockquote', label: 'Quote' },
      { value: 'pre', label: 'Code block' }
    ]));
    blockGroup.appendChild(createSelect('fontName', 'Font family', [
      { value: 'Arial', label: 'Arial' },
      { value: 'Georgia', label: 'Georgia' },
      { value: 'Tahoma', label: 'Tahoma' },
      { value: 'Verdana', label: 'Verdana' }
    ]));
    toolbar.appendChild(blockGroup);

    var formatGroup = document.createElement('div');
    formatGroup.className = 'spe-toolbar-group';
    [
      ['B', 'bold', 'Bold'],
      ['I', 'italic', 'Italic'],
      ['U', 'underline', 'Underline'],
      ['S', 'strikeThrough', 'Strike through'],
      ['x²', 'superscript', 'Superscript'],
      ['x₂', 'subscript', 'Subscript']
    ].forEach(function (item) {
      formatGroup.appendChild(createButton(item[0], item[1], item[2]));
    });

    var color = document.createElement('input');
    color.type = 'color';
    color.value = '#10243e';
    color.className = 'spe-color-input';
    color.setAttribute('data-spe-color', 'foreColor');
    color.setAttribute('aria-label', 'Text color');
    formatGroup.appendChild(color);
    toolbar.appendChild(formatGroup);

    var listGroup = document.createElement('div');
    listGroup.className = 'spe-toolbar-group';
    [
      ['• List', 'insertUnorderedList', 'Bullet list'],
      ['1. List', 'insertOrderedList', 'Numbered list'],
      ['←', 'outdent', 'Decrease indent'],
      ['→', 'indent', 'Increase indent']
    ].forEach(function (item) {
      listGroup.appendChild(createButton(item[0], item[1], item[2]));
    });
    toolbar.appendChild(listGroup);

    var alignGroup = document.createElement('div');
    alignGroup.className = 'spe-toolbar-group';
    [
      ['≡', 'justifyLeft', 'Align left'],
      ['≣', 'justifyCenter', 'Align center'],
      ['≡', 'justifyRight', 'Align right']
    ].forEach(function (item) {
      alignGroup.appendChild(createButton(item[0], item[1], item[2]));
    });
    toolbar.appendChild(alignGroup);

    var insertGroup = document.createElement('div');
    insertGroup.className = 'spe-toolbar-group';
    [
      ['🔗', 'link', 'Insert or edit link'],
      ['⌁', 'unlink', 'Remove link'],
      ['▧', 'table', 'Insert table'],
      ['▣', 'image', 'Insert image from URL'],
      ['―', 'horizontalRule', 'Insert divider'],
      ['</>', 'inlineCode', 'Inline code']
    ].forEach(function (item) {
      insertGroup.appendChild(createButton(item[0], item[1], item[2]));
    });
    toolbar.appendChild(insertGroup);

    var historyGroup = document.createElement('div');
    historyGroup.className = 'spe-toolbar-group';
    [
      ['↶', 'undo', 'Undo'],
      ['↷', 'redo', 'Redo'],
      ['Tx', 'removeFormat', 'Clear formatting']
    ].forEach(function (item) {
      historyGroup.appendChild(createButton(item[0], item[1], item[2]));
    });
    toolbar.appendChild(historyGroup);

    return toolbar;
  }

  function insertHtml(canvas, markup) {
    canvas.focus();

    if (document.execCommand) {
      document.execCommand('insertHTML', false, markup);
      return;
    }

    var selection = window.getSelection();

    if (!selection || !selection.rangeCount) {
      canvas.insertAdjacentHTML('beforeend', markup);
      return;
    }

    var range = selection.getRangeAt(0);
    range.deleteContents();

    var fragment = range.createContextualFragment(markup);
    range.insertNode(fragment);
    range.collapse(false);
  }

  function command(canvas, action, value) {
    canvas.focus();

    if (action === 'inlineCode') {
      var selection = window.getSelection();

      if (!selection || !selection.rangeCount || selection.isCollapsed) {
        return;
      }

      var range = selection.getRangeAt(0);
      var selectedText = range.toString();

      if (!selectedText) {
        return;
      }

      range.deleteContents();

      var code = document.createElement('code');
      code.textContent = selectedText;
      range.insertNode(code);
      return;
    }

    if (action === 'link') {
      var selected = window.getSelection ? window.getSelection().toString() : '';
      var link = window.prompt('Enter the full URL for this link:', 'https://');

      if (!link || !isSafeUrl(link, false)) {
        return;
      }

      if (!selected) {
        var linkText = window.prompt('Text to display for the link:', link);

        if (!linkText) {
          return;
        }

        insertHtml(canvas, '<a href="' + escapeHtml(link) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(linkText) + '</a>');
      } else {
        document.execCommand('createLink', false, link);
      }

      return;
    }

    if (action === 'image') {
      var imageUrl = window.prompt('Enter the image URL:', 'https://');

      if (!imageUrl || !isSafeUrl(imageUrl, true)) {
        return;
      }

      var altText = window.prompt('Image description (optional):', '');
      insertHtml(
        canvas,
        '<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(altText || '') + '" loading="lazy">'
      );
      return;
    }

    if (action === 'table') {
      var rows = parseInt(window.prompt('How many table rows?', '3'), 10);
      var columns = parseInt(window.prompt('How many table columns?', '3'), 10);

      if (!rows || !columns || rows < 1 || columns < 1 || rows > 12 || columns > 12) {
        return;
      }

      var table = '<table><tbody>';

      for (var row = 0; row < rows; row += 1) {
        table += '<tr>';

        for (var column = 0; column < columns; column += 1) {
          table += row === 0 ? '<th>Heading</th>' : '<td><br></td>';
        }

        table += '</tr>';
      }

      table += '</tbody></table><p><br></p>';
      insertHtml(canvas, table);
      return;
    }

    if (action === 'horizontalRule') {
      document.execCommand('insertHorizontalRule', false, null);
      return;
    }

    if (action === 'formatBlock') {
      document.execCommand('formatBlock', false, value || 'p');
      return;
    }

    if (action === 'fontName') {
      document.execCommand('fontName', false, value || 'Arial');
      return;
    }

    document.execCommand(action, false, value || null);
  }

  function mountEditor(host) {
    var sourceId = host.getAttribute('data-source-id');
    var source = document.getElementById(sourceId);

    if (!source || host.dataset.editorMounted === '1') {
      return;
    }

    host.dataset.editorMounted = '1';

    var shell = document.createElement('div');
    shell.className = 'spe-shell';

    var topbar = document.createElement('div');
    topbar.className = 'spe-topbar';

    var modeGroup = document.createElement('div');
    modeGroup.className = 'spe-mode-group';

    var sourceButton = createButton('</> Source', 'sourceMode', 'View and edit HTML source', 'spe-text-button');
    var previewButton = createButton('◉ Preview', 'previewMode', 'Preview the saved HTML', 'spe-text-button');
    modeGroup.appendChild(sourceButton);
    modeGroup.appendChild(previewButton);

    var wordCount = document.createElement('span');
    wordCount.className = 'spe-word-count';

    topbar.appendChild(modeGroup);
    topbar.appendChild(wordCount);

    var toolbar = createToolbar();

    var stage = document.createElement('div');
    stage.className = 'spe-stage';

    var canvas = document.createElement('div');
    canvas.className = 'spe-canvas';
    canvas.contentEditable = 'true';
    canvas.setAttribute('role', 'textbox');
    canvas.setAttribute('aria-multiline', 'true');
    canvas.setAttribute('aria-label', host.getAttribute('data-editor-label') || 'Page content editor');
    canvas.setAttribute('data-placeholder', 'Write page content here…');
    canvas.innerHTML = sanitizeHtml(source.value || '');

    var sourceView = document.createElement('textarea');
    sourceView.className = 'spe-source-view';
    sourceView.setAttribute('aria-label', 'HTML source code');
    sourceView.spellcheck = false;

    var preview = document.createElement('div');
    preview.className = 'spe-preview';
    preview.setAttribute('aria-label', 'Page content preview');

    stage.appendChild(canvas);
    stage.appendChild(sourceView);
    stage.appendChild(preview);

    shell.appendChild(topbar);
    shell.appendChild(toolbar);
    shell.appendChild(stage);
    host.appendChild(shell);

    var mode = 'edit';

    function getCurrentHtml() {
      if (mode === 'source') {
        return sourceView.value;
      }

      return canvas.innerHTML;
    }

    function syncSource() {
      source.value = getCurrentHtml();
      wordCount.textContent = getTextWordCount(source.value) + ' word' + (getTextWordCount(source.value) === 1 ? '' : 's');
    }

    function setMode(nextMode) {
      if (nextMode === mode) {
        nextMode = 'edit';
      }

      if (mode === 'source' && nextMode !== 'source') {
        canvas.innerHTML = sanitizeHtml(sourceView.value);
      }

      if (nextMode === 'source') {
        sourceView.value = canvas.innerHTML;
      }

      if (nextMode === 'preview') {
        preview.innerHTML = sanitizeHtml(getCurrentHtml());
      }

      mode = nextMode;
      shell.classList.toggle('is-source', mode === 'source');
      shell.classList.toggle('is-preview', mode === 'preview');
      sourceButton.classList.toggle('is-active', mode === 'source');
      previewButton.classList.toggle('is-active', mode === 'preview');

      syncSource();
    }

    function afterCommand() {
      syncSource();
      canvas.focus();
    }

    topbar.addEventListener('click', function (event) {
      var button = event.target.closest('[data-spe-action]');

      if (!button) {
        return;
      }

      var action = button.getAttribute('data-spe-action');

      if (action === 'sourceMode') {
        setMode('source');
      }

      if (action === 'previewMode') {
        setMode('preview');
      }
    });

    toolbar.addEventListener('click', function (event) {
      var button = event.target.closest('[data-spe-action]');

      if (!button) {
        return;
      }

      command(canvas, button.getAttribute('data-spe-action'));
      afterCommand();
    });

    toolbar.addEventListener('change', function (event) {
      var select = event.target.closest('[data-spe-select]');

      if (!select) {
        return;
      }

      command(canvas, select.getAttribute('data-spe-select'), select.value);
      afterCommand();
    });

    toolbar.addEventListener('input', function (event) {
      var color = event.target.closest('[data-spe-color]');

      if (!color) {
        return;
      }

      command(canvas, color.getAttribute('data-spe-color'), color.value);
      afterCommand();
    });

    canvas.addEventListener('input', syncSource);
    sourceView.addEventListener('input', syncSource);

    canvas.addEventListener('paste', function (event) {
      var clipboard = event.clipboardData || window.clipboardData;

      if (!clipboard) {
        return;
      }

      var html = clipboard.getData('text/html');
      var plain = clipboard.getData('text/plain');

      event.preventDefault();

      if (html) {
        insertHtml(canvas, sanitizeHtml(html));
      } else {
        insertHtml(canvas, escapeHtml(plain).replace(/\r?\n/g, '<br>'));
      }

      syncSource();
    });

    source.addEventListener('change', function () {
      if (mode !== 'source') {
        canvas.innerHTML = sanitizeHtml(source.value);
      }

      syncSource();
    });

    var form = closestForm(host);

    if (form) {
      form.addEventListener('submit', function () {
        if (mode === 'source') {
          source.value = sourceView.value;
        } else {
          source.value = canvas.innerHTML;
        }
      });
    }

    syncSource();
  }

  function initialise() {
    Array.prototype.slice.call(document.querySelectorAll('[data-static-page-editor]')).forEach(mountEditor);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialise);
  } else {
    initialise();
  }
}());
