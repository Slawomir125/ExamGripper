(function () {
  function getPageData() {
    const el = document.getElementById("page-data");
    if (!el) return {};

    try {
      return JSON.parse(el.textContent || "{}") || {};
    } catch {
      return {};
    }
  }

  function valueToString(value) {
    if (value == null) return "";

    if (typeof value === "object") {
      try {
        return JSON.stringify(value);
      } catch {
        return String(value);
      }
    }

    return String(value);
  }

  function resolvePath(obj, path) {
    if (path === "" || path === ".") return obj;

    const parts = path.split(".");
    let current = obj;

    for (let i = 0; i < parts.length; i++) {
      if (current == null) return null;
      current = current[parts[i]];
    }

    return current;
  }

  function resolveExpr(ctx, expr) {
    expr = expr.trim();

    if (expr === ".") return ctx.current;

    // {{*.name}} -> parent.name
    // {{**.name}} -> parent parent .name
    // {{*}} -> cały parent
    if (expr.startsWith("*")) {
      let level = 0;

      while (expr[level] === "*") {
        level++;
      }

      let target = ctx;
      for (let i = 0; i < level; i++) {
        if (!target || !target.parent) return null;
        target = target.parent;
      }

      let rest = expr.substring(level);
      if (rest.startsWith(".")) {
        rest = rest.substring(1);
      }

      if (rest === "") {
        return target.current;
      }

      return resolvePath(target.current, rest);
    }

    return resolvePath(ctx.current, expr);
  }

  function tokenReplace(text, ctx, index) {
    return String(text).replace(/\{\{\s*([^}]+)\s*\}\}/g, function (_, expr) {
      expr = expr.trim();

      if (expr === "$") return String(index);
      if (expr === "%") return String(index + 1);

      const value = resolveExpr(ctx, expr);
      return valueToString(value);
    });
  }

  function removeTemplateAttributes(root) {
    if (root.hasAttribute && root.hasAttribute("template")) {
      root.removeAttribute("template");
    }

    root.querySelectorAll("[template]").forEach(function (el) {
      el.removeAttribute("template");
    });
  }

  function cloneTemplate(template) {
    const node = template.cloneNode(true);
    removeTemplateAttributes(node);
    return node;
  }

  function getTemplate(container) {
    let template = container.querySelector("[template]");
    if (!template) {
      template = container.firstElementChild;
    }
    return template;
  }

  function shouldSkipChildTree(el) {
    if (!el || !el.hasAttribute) return false;

    return (
      el.hasAttribute("innerbinding") ||
      el.hasAttribute("binding") ||
      el.hasAttribute("defaultbinding")
    );
  }

  function applyBindings(root, ctx, index) {
    function walk(node) {
      // tekst
      if (node.nodeType === Node.TEXT_NODE) {
        node.nodeValue = tokenReplace(node.nodeValue, ctx, index);
        return;
      }

      // tylko elementy
      if (node.nodeType !== Node.ELEMENT_NODE) {
        return;
      }

      // atrybuty tego elementu
      const attrs = Array.from(node.attributes || []);
      for (const attr of attrs) {
        const name = attr.name.toLowerCase();
        const value = attr.value;

        if (
          name === "template" ||
          name === "binding" ||
          name === "defaultbinding" ||
          name === "innerbinding" ||
          name === "nullable"
        ) {
          continue;
        }

        if (!value || value.indexOf("{{") === -1) continue;

        const replaced = tokenReplace(value, ctx, index);

        const tagName = (node.tagName || "").toLowerCase();
        if ((tagName === "input" || tagName === "textarea") && name === "value") {
          node.value = replaced;
        }

        node.setAttribute(attr.name, replaced);
      }

      // NIE wchodź do wnętrza nested bindingów
      for (const child of Array.from(node.childNodes)) {
        if (child.nodeType === Node.ELEMENT_NODE && shouldSkipChildTree(child)) {
          continue;
        }

        walk(child);
      }
    }

    walk(root);
  }

  function renderInnerBindings(root, ctx) {
    root.querySelectorAll("[innerbinding]").forEach(function (innerContainer) {
      const expr = (innerContainer.getAttribute("innerbinding") || "").trim();
      const cleanExpr = expr.replace(/^\{\{|\}\}$/g, "").trim();
      const innerData = resolveExpr(ctx, cleanExpr);

      const innerTemplate = getTemplate(innerContainer);
      if (!innerTemplate) {
        innerContainer.innerHTML = "";
        return;
      }

      const templateClone = innerTemplate.cloneNode(true);
      innerContainer.innerHTML = "";

      if (Array.isArray(innerData)) {
        for (let i = 0; i < innerData.length; i++) {
          const childCtx = {
            current: innerData[i],
            parent: ctx
          };

          const item = cloneTemplate(templateClone);
          applyBindings(item, childCtx, i);
          renderInnerBindings(item, childCtx);
          innerContainer.appendChild(item);
        }
        return;
      }

      if (innerData && typeof innerData === "object") {
        const childCtx = {
          current: innerData,
          parent: ctx
        };

        const item = cloneTemplate(templateClone);
        applyBindings(item, childCtx, 0);
        renderInnerBindings(item, childCtx);
        innerContainer.appendChild(item);
        return;
      }

      innerContainer.innerHTML = "";
    });
  }

  function render(compiled, value) {
    compiled.container.innerHTML = "";

    if (value == null) {
      return;
    }

    if (Array.isArray(value)) {
      for (let i = 0; i < value.length; i++) {
        const ctx = {
          current: value[i],
          parent: null
        };

        const item = cloneTemplate(compiled.template);
        applyBindings(item, ctx, i);
        renderInnerBindings(item, ctx);
        compiled.container.appendChild(item);
      }
      return;
    }

    if (typeof value === "object") {
      const ctx = {
        current: value,
        parent: null
      };

      const item = cloneTemplate(compiled.template);
      applyBindings(item, ctx, 0);
      renderInnerBindings(item, ctx);
      compiled.container.appendChild(item);
    }
  }

  function compileContainer(container, key, autoLoad) {
    const template = getTemplate(container);
    if (!template) return null;

    const templateClone = template.cloneNode(true);
    container.innerHTML = "";

    return {
      key: key,
      container: container,
      template: templateClone,
      autoLoad: autoLoad
    };
  }

  const store = new Map();

  function scanBindings() {
    document.querySelectorAll("[defaultbinding]").forEach(function (container) {
      const key = container.getAttribute("defaultbinding");
      if (!key) return;

      const compiled = compileContainer(container, key, true);
      if (!compiled) return;

      store.set(key, compiled);
    });

    document.querySelectorAll("[binding]").forEach(function (container) {
      const key = container.getAttribute("binding");
      if (!key) return;

      if (store.has(key)) return;

      const compiled = compileContainer(container, key, false);
      if (!compiled) return;

      store.set(key, compiled);
    });
  }

  window.Binding = {
    init: function () {
      scanBindings();

      const pageData = getPageData();

      store.forEach(function (compiled, key) {
        if (!compiled.autoLoad) return;
        render(compiled, pageData[key]);
      });
    },

    set: function (key, value) {
      const compiled = store.get(key);
      if (!compiled) return;

      render(compiled, value);
    }
  };
})();