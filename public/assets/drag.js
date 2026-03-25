(function () {
  const normalizeSelector = window.normalizeSelector;
  const getElement = window.getElement;
  const getElements = window.getElements;

  function getDropContainers() {
    return getElements("[drop]");
  }

  function getDragItems(container) {
    if (!container) return [];

    return Array.from(container.children).filter(function (el) {
      return el.hasAttribute("drag") && el.hasAttribute("drag-value");
    });
  }

  function getValue(el) {
    return el.getAttribute("drag-value") || "";
  }

  function isDisabled(el) {
    return (el.getAttribute("drag-disabled") || "").toLowerCase() === "true";
  }

  function hasHandle(el) {
    return !!el.querySelector("[drag-handle]");
  }

  function startedFromHandle(item, target) {
    if (!hasHandle(item)) return true;
    return !!target.closest("[drag-handle]");
  }

  function getDropMode(container) {
    return (container.getAttribute("drop-mode") || "sort").toLowerCase();
  }

  function getGroup(container) {
    return container.getAttribute("drag-group") || "";
  }

  function canMoveBetween(fromContainer, toContainer) {
    if (!fromContainer || !toContainer) return false;
    if (fromContainer === toContainer) return true;

    const fromGroup = getGroup(fromContainer);
    const toGroup = getGroup(toContainer);

    if (!fromGroup || !toGroup) return false;
    return fromGroup === toGroup;
  }

  const state = {
    draggedEl: null,
    sourceContainer: null,
    sourceInstance: null,
    targetContainer: null,
    targetInstance: null,
    ghostEl: null,
    changedContainers: new Set(),
    pointerAllowed: false,
    pointerItem: null
  };

  function createGhost(sourceEl, targetContainer) {
    const useGhost = (targetContainer.getAttribute("drop-ghost") || "").toLowerCase() === "true";
    if (!useGhost) return null;

    const ghost = sourceEl.cloneNode(true);
    const sourceRect = sourceEl.getBoundingClientRect();
    const opacity = targetContainer.getAttribute("drop-ghost-opacity");

    ghost.removeAttribute("id");
    ghost.removeAttribute("drag");
    ghost.removeAttribute("drag-value");
    ghost.removeAttribute("drag-disabled");
    ghost.removeAttribute("draggable");

    ghost.querySelectorAll("[id]").forEach(function (el) {
      el.removeAttribute("id");
    });

    ghost.querySelectorAll("[drag],[drag-value],[drag-disabled],[draggable],[drag-handle]").forEach(function (el) {
      el.removeAttribute("drag");
      el.removeAttribute("drag-value");
      el.removeAttribute("drag-disabled");
      el.removeAttribute("draggable");
      el.removeAttribute("drag-handle");
    });

    ghost.style.pointerEvents = "none";
    ghost.style.visibility = "visible";
    ghost.style.display = "";
    ghost.style.opacity = opacity ? String(opacity) : "0.35";
    ghost.style.minHeight = sourceRect.height + "px";

    Array.from(ghost.querySelectorAll("*"))
      .concat([ghost])
      .forEach(function (el) {
        el.style.visibility = "visible";
      });

    return ghost;
  }

  function removeGhost() {
    if (state.ghostEl && state.ghostEl.parentNode) {
      state.ghostEl.parentNode.removeChild(state.ghostEl);
    }
    state.ghostEl = null;
  }

  function setTargetContainer(container) {
    if (!container) return;

    if (state.targetContainer !== container) {
      removeGhost();
      state.targetContainer = container;
      state.targetInstance = container._fwDragInstance || null;
    }
  }

  function ensureGhost(container) {
    setTargetContainer(container);

    if (!state.ghostEl) {
      state.ghostEl = createGhost(state.draggedEl, container);
    }

    return state.ghostEl;
  }

  function clearState() {
    removeGhost();

    if (state.draggedEl) {
      state.draggedEl.style.visibility = "";
      state.draggedEl.style.opacity = "";
    }

    state.draggedEl = null;
    state.sourceContainer = null;
    state.sourceInstance = null;
    state.targetContainer = null;
    state.targetInstance = null;
    state.changedContainers.clear();
    state.pointerAllowed = false;
    state.pointerItem = null;
  }

  function findDropContainer(target) {
    if (!target) return null;
    if (target.hasAttribute && target.hasAttribute("drop")) return target;
    if (target.closest) return target.closest("[drop]");
    return null;
  }

  function findDropContainerFromPoint(x, y) {
    if (typeof document.elementsFromPoint !== "function") {
      return null;
    }

    const elements = document.elementsFromPoint(x, y);

    for (let i = 0; i < elements.length; i++) {
      const el = elements[i];
      if (!el) continue;
      if (el === state.draggedEl || el === state.ghostEl) continue;

      if (el.hasAttribute && el.hasAttribute("drop")) {
        return el;
      }

      if (el.closest) {
        const container = el.closest("[drop]");
        if (container) {
          return container;
        }
      }
    }

    return null;
  }

  function getInsertBefore(container, y, draggedEl, ghostEl) {
    const items = getDragItems(container).filter(function (el) {
      return el !== draggedEl && el !== ghostEl;
    });

    if (items.length === 0) {
      return null;
    }

    for (let i = 0; i < items.length; i++) {
      const rect = items[i].getBoundingClientRect();
      const middle = rect.top + rect.height / 2;

      if (y < middle) {
        return items[i];
      }
    }

    return null;
  }

  function getCurrentNextSibling(container, draggedEl) {
    const items = getDragItems(container);
    const index = items.indexOf(draggedEl);
    if (index === -1) return null;
    return items[index + 1] || null;
  }

  function buildInfo(element) {
    return {
      value: getValue(element),
      element: element
    };
  }

  function buildContainerInfo(container) {
    return {
      element: container,
      values: getDragItems(container).map(getValue)
    };
  }

  function Drag(containerSelector) {
    const container = getElement(containerSelector);

    if (!container) {
      console.warn("Drag: nie znaleziono kontenera:", containerSelector);
      return;
    }

    if (container._fwDragInstance) {
      return container._fwDragInstance;
    }

    this.container = container;
    this.enabled = true;
    this.onStart = null;
    this.onEnd = null;
    this.onChange = null;

    this._bind();
    this._prepareItems();

    container._fwDragInstance = this;
  }

  Drag.prototype._prepareItems = function () {
    const self = this;

    getDragItems(this.container).forEach(function (item) {
      item.draggable = self.enabled && !isDisabled(item);
      item.style.webkitUserDrag = self.enabled && !isDisabled(item) ? "element" : "none";
      item.style.cursor = isDisabled(item) ? "default" : "grab";
    });
  };

  Drag.prototype._bind = function () {
    const self = this;
    const container = this.container;

    container.addEventListener("mousedown", function (e) {
      const item = e.target.closest("[drag]");
      if (!item || !container.contains(item)) return;
      if (!self.enabled || isDisabled(item)) return;

      state.pointerItem = item;
      state.pointerAllowed = startedFromHandle(item, e.target);
    });

    container.addEventListener("dragstart", function (e) {
      const item = e.target.closest("[drag]");
      if (!item || !container.contains(item)) return;

      if (!self.enabled || isDisabled(item)) {
        e.preventDefault();
        return;
      }

      if (hasHandle(item) && (!state.pointerAllowed || state.pointerItem !== item)) {
        e.preventDefault();
        return;
      }

      state.draggedEl = item;
      state.sourceContainer = container;
      state.sourceInstance = self;
      state.targetContainer = container;
      state.targetInstance = self;
      state.changedContainers.clear();

      try {
        e.dataTransfer.effectAllowed = "move";
        e.dataTransfer.setData("text/plain", getValue(item));
      } catch {}

      setTimeout(function () {
        if (state.draggedEl === item) {
          item.style.visibility = "hidden";
        }
      }, 0);

      if (typeof self.onStart === "function") {
        self.onStart(buildInfo(item), buildContainerInfo(container));
      }
    });

    container.addEventListener("dragend", function () {
      if (!state.draggedEl) {
        state.pointerAllowed = false;
        state.pointerItem = null;
        return;
      }

      const draggedEl = state.draggedEl;
      const sourceContainer = state.sourceContainer;
      const sourceInstance = state.sourceInstance;
      const targetContainer = state.targetContainer || sourceContainer;
      const targetInstance = state.targetInstance || sourceInstance;
      const changed = state.changedContainers.size > 0;

      if (typeof sourceInstance?.onEnd === "function") {
        sourceInstance.onEnd(
          buildInfo(draggedEl),
          buildContainerInfo(sourceContainer),
          buildContainerInfo(targetContainer)
        );
      }

      if (changed) {
        if (sourceInstance && typeof sourceInstance.onChange === "function") {
          sourceInstance.onChange(
            sourceInstance.get(),
            buildContainerInfo(sourceContainer),
            buildContainerInfo(targetContainer)
          );
        }

        if (targetInstance && targetInstance !== sourceInstance && typeof targetInstance.onChange === "function") {
          targetInstance.onChange(
            targetInstance.get(),
            buildContainerInfo(sourceContainer),
            buildContainerInfo(targetContainer)
          );
        }
      }

      clearState();
    });
  };

  Drag.prototype.get = function () {
    return getDragItems(this.container).map(getValue);
  };

  Drag.prototype.enable = function () {
    this.enabled = true;
    this._prepareItems();
  };

  Drag.prototype.disable = function () {
    this.enabled = false;
    this._prepareItems();
  };

  Drag.prototype.destroy = function () {
    this.disable();
    this.container._fwDragInstance = null;
  };

  document.addEventListener("dragover", function (e) {
    if (!state.draggedEl || !state.sourceContainer) return;

    const container = findDropContainerFromPoint(e.clientX, e.clientY) || findDropContainer(e.target) || state.targetContainer;
    if (!container) return;
    if (!canMoveBetween(state.sourceContainer, container)) return;

    e.preventDefault();

    const mode = getDropMode(container);

    if (mode === "append") {
      const ghost = ensureGhost(container);

      if (ghost && container.lastElementChild !== ghost) {
        container.appendChild(ghost);
      }

      return;
    }

    const ghost = ensureGhost(container);
    if (!ghost) {
      return;
    }

    const before = getInsertBefore(container, e.clientY, state.draggedEl, ghost);

    if (container === state.sourceContainer) {
      const currentNext = getCurrentNextSibling(container, state.draggedEl);
      const noMove = before === currentNext || (!before && currentNext === null);

      if (noMove) {
        removeGhost();
        state.targetContainer = container;
        state.targetInstance = container._fwDragInstance || null;
        return;
      }
    }

    if (before) {
      if (before !== ghost) {
        container.insertBefore(ghost, before);
      }
    } else {
      container.appendChild(ghost);
    }
  });

  document.addEventListener("drop", function (e) {
    if (!state.draggedEl || !state.sourceContainer) return;

    const container = findDropContainerFromPoint(e.clientX, e.clientY) || state.targetContainer || findDropContainer(e.target);
    if (!container) return;
    if (!canMoveBetween(state.sourceContainer, container)) return;

    e.preventDefault();

    setTargetContainer(container);

    const mode = getDropMode(container);

    if (mode === "append") {
      container.appendChild(state.draggedEl);
    } else if (state.ghostEl && state.ghostEl.parentNode === container) {
      container.insertBefore(state.draggedEl, state.ghostEl);
    } else {
      const before = getInsertBefore(container, e.clientY, state.draggedEl, state.ghostEl);
      if (before) {
        container.insertBefore(state.draggedEl, before);
      } else {
        container.appendChild(state.draggedEl);
      }
    }

    removeGhost();

    state.changedContainers.add(state.sourceContainer);
    state.changedContainers.add(container);
    state.targetContainer = container;
    state.targetInstance = container._fwDragInstance || null;
  });

  document.addEventListener("mouseup", function () {
    state.pointerAllowed = false;
    state.pointerItem = null;
  });

  window.Drag = Drag;

  document.addEventListener("DOMContentLoaded", function () {
    getDropContainers().forEach(function (container) {
      new Drag(container);
    });
  });
})();