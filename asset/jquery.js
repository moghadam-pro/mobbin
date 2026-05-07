/* Minimal jQuery-like helper (local) */
(function (window) {
  function JQ(nodes) {
    this.nodes = nodes || [];
  }

  JQ.prototype.each = function (fn) {
    this.nodes.forEach(function (node, idx) {
      fn.call(node, idx, node);
    });
    return this;
  };

  JQ.prototype.on = function (event, handler) {
    return this.each(function () {
      this.addEventListener(event, handler);
    });
  };

  JQ.prototype.click = function (handler) {
    return this.on('click', handler);
  };

  JQ.prototype.val = function (value) {
    if (value === undefined) {
      return this.nodes[0] ? this.nodes[0].value : undefined;
    }
    return this.each(function () {
      this.value = value;
    });
  };

  JQ.prototype.text = function (value) {
    if (value === undefined) {
      return this.nodes[0] ? this.nodes[0].textContent : undefined;
    }
    return this.each(function () {
      this.textContent = value;
    });
  };

  JQ.prototype.html = function (value) {
    if (value === undefined) {
      return this.nodes[0] ? this.nodes[0].innerHTML : undefined;
    }
    return this.each(function () {
      this.innerHTML = value;
    });
  };

  JQ.prototype.attr = function (name, value) {
    if (value === undefined) {
      return this.nodes[0] ? this.nodes[0].getAttribute(name) : undefined;
    }
    return this.each(function () {
      this.setAttribute(name, value);
    });
  };

  JQ.prototype.data = function (name) {
    if (!this.nodes[0]) return undefined;
    return this.nodes[0].dataset ? this.nodes[0].dataset[name] : undefined;
  };

  JQ.prototype.addClass = function (className) {
    return this.each(function () {
      this.classList.add(className);
    });
  };

  JQ.prototype.removeClass = function (className) {
    return this.each(function () {
      this.classList.remove(className);
    });
  };

  JQ.prototype.toggleClass = function (className, force) {
    return this.each(function () {
      this.classList.toggle(className, force);
    });
  };

  JQ.prototype.show = function () {
    return this.each(function () {
      this.style.display = '';
    });
  };

  JQ.prototype.hide = function () {
    return this.each(function () {
      this.style.display = 'none';
    });
  };

  JQ.prototype.find = function (selector) {
    if (!this.nodes[0]) return new JQ([]);
    return new JQ(Array.from(this.nodes[0].querySelectorAll(selector)));
  };

  JQ.prototype.closest = function (selector) {
    if (!this.nodes[0]) return new JQ([]);
    var node = this.nodes[0].closest(selector);
    return new JQ(node ? [node] : []);
  };

  function $(selector) {
    if (typeof selector === 'function') {
      if (document.readyState !== 'loading') {
        selector();
      } else {
        document.addEventListener('DOMContentLoaded', selector);
      }
      return;
    }

    if (selector instanceof JQ) {
      return selector;
    }

    if (selector === window || selector === document || selector instanceof Node) {
      return new JQ([selector]);
    }

    var nodes = Array.from(document.querySelectorAll(selector));
    return new JQ(nodes);
  }

  window.$ = $;
  window.jQuery = $;
})(window);
