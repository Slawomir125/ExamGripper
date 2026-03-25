function normalizeSelector(selector)
{
    if (!selector) return "";

    selector = String(selector).trim();
    if (selector === "") return "";

    const first = selector[0];

    if (
        first === "#" ||
        first === "." ||
        first === "[" ||
        first === "*" ||
        first === ":" ||
        first === ">" ||
        first === "+" ||
        first === "~"
    ) {
        return selector;
    }

    if (
        selector.includes(" ") ||
        selector.includes("[") ||
        selector.includes(":") ||
        selector.includes(">") ||
        selector.includes("+") ||
        selector.includes("~") ||
        selector.includes(".") ||
        selector.includes("#")
    ) {
        return selector;
    }

    return "#" + selector;
}

function getElement(selector)
{
    if (!selector) return null;

    if (selector instanceof Element) {
        return selector;
    }

    const cssSelector = window.normalizeSelector(selector);
    if (!cssSelector) return null;

    return document.querySelector(cssSelector);
}

function getElements(selector)
{
    if (!selector) return [];

    if (selector instanceof Element) {
        return [selector];
    }

    if (selector instanceof NodeList || Array.isArray(selector)) {
        return Array.from(selector);
    }

    const cssSelector = window.normalizeSelector(selector);
    if (!cssSelector) return [];

    return Array.from(document.querySelectorAll(cssSelector));
}

function onReady(callback)
{
    if (typeof callback !== "function") {
        return;
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", callback, { once: true });
        return;
    }

    callback();
}

window.normalizeSelector = window.normalizeSelector || normalizeSelector;
window.getElement = window.getElement || getElement;
window.getElements = window.getElements || getElements;
window.onReady = window.onReady || onReady;

(function ()
{
    function run(handler)
    {
        try {
            const result = handler();

            if (result && typeof result.then === "function") {
                result.catch(console.error);
            }
        } catch (error) {
            console.error(error);
        }
    }

    function getAttributeSelector(name)
    {
        name = String(name || "").trim();

        if (name === "") {
            return "";
        }

        if (name.startsWith("data-")) {
            return "[" + name + "]";
        }

        return "[" + name + "], [data-" + name + "]";
    }

    function getAttributeValue(element, name)
    {
        name = String(name || "").trim();

        if (name === "") {
            return null;
        }

        if (name.startsWith("data-")) {
            return element.getAttribute(name);
        }

        if (element.hasAttribute(name)) {
            return element.getAttribute(name);
        }

        return element.getAttribute("data-" + name);
    }

    function addDelegatedEvent(eventName, selector, handler)
    {
        const cssSelector = window.normalizeSelector(selector);
        if (!cssSelector) return;

        document.addEventListener(eventName, (e) =>
        {
            const element = e.target.closest(cssSelector);
            if (!element) return;

            run(() => handler(element, e));
        });
    }

    function Tabs(containerSelector, startTab)
    {
        this.container = window.getElement(containerSelector);
        this.onChange = null;

        if (!this.container) {
            console.warn("Tabs: nie znaleziono kontenera:", containerSelector);
            return;
        }

        this.buttons = Array.from(
            this.container.querySelectorAll('button[set-tab], input[type="button"][set-tab]')
        );

        this.panels = Array.from(this.container.querySelectorAll("[tab]"));

        this.buttons.forEach((button) =>
        {
            button.addEventListener("click", (e) =>
            {
                e.preventDefault();
                this.set(button.getAttribute("set-tab"));
            });
        });

        if (startTab !== undefined && startTab !== null) {
            this.set(startTab);
            return;
        }

        const activeButton = this.buttons.find((button) =>
        {
            return button.classList.contains("active");
        });

        if (activeButton) {
            this.set(activeButton.getAttribute("set-tab"));
            return;
        }

        if (this.panels.length > 0) {
            this.set(0);
        }
    }

    Tabs.prototype._getButtons = function ()
    {
        return this.buttons || [];
    };

    Tabs.prototype._getPanels = function ()
    {
        return this.panels || [];
    };

    Tabs.prototype._getIndexById = function (tabId)
    {
        const panels = this._getPanels();

        for (let i = 0; i < panels.length; i++) {
            if ((panels[i].getAttribute("tab") || "") === String(tabId)) {
                return i;
            }
        }

        return -1;
    };

    Tabs.prototype._getIndex = function (tab)
    {
        if (typeof tab === "number" && !Number.isNaN(tab)) {
            return tab;
        }

        return this._getIndexById(tab);
    };

    Tabs.prototype.set = function (tab)
    {
        if (!this.container) return;

        const buttons = this._getButtons();
        const panels = this._getPanels();

        if (panels.length === 0) return;

        const index = this._getIndex(tab);

        if (index < 0 || index >= panels.length) {
            return;
        }

        const activeId = panels[index].getAttribute("tab") || "";

        buttons.forEach((button) =>
        {
            const active = (button.getAttribute("set-tab") || "") === activeId;
            button.classList.toggle("active", active);
            button.setAttribute("aria-selected", active ? "true" : "false");
        });

        panels.forEach((panel, i) =>
        {
            const active = i === index;

            panel.style.display = active ? "" : "none";
            panel.classList.toggle("active", active);
            panel.setAttribute("aria-hidden", active ? "false" : "true");
        });

        this.activeId = activeId;
        this.activeIndex = index;

        if (typeof this.onChange === "function") {
            this.onChange(activeId, index);
        }
    };

    Tabs.prototype.get = function ()
    {
        return this.activeId || "";
    };

    Tabs.prototype.getIndex = function ()
    {
        return typeof this.activeIndex === "number" ? this.activeIndex : -1;
    };

    Tabs.prototype.next = function ()
    {
        const panels = this._getPanels();
        if (panels.length === 0) return;

        let index = this.getIndex();
        if (index < 0) index = 0;

        index++;
        if (index >= panels.length) index = 0;

        this.set(index);
    };

    Tabs.prototype.prev = function ()
    {
        const panels = this._getPanels();
        if (panels.length === 0) return;

        let index = this.getIndex();
        if (index < 0) index = 0;

        index--;
        if (index < 0) index = panels.length - 1;

        this.set(index);
    };

    window.getPageData = function ()
    {
        const el = document.getElementById("page-data");
        if (!el) return {};

        try {
            return JSON.parse(el.textContent || "{}") || {};
        } catch {
            return {};
        }
    };

    window.setText = function (selector, value)
    {
        const element = window.getElement(selector);
        if (!element) return;

        element.textContent = value ?? "";
    };

    window.hide = function (selector, hidden = true)
    {
        const element = window.getElement(selector);
        if (!element) return;

        if (hidden) {
            if (element.style.display !== "none") {
                element.dataset.oldDisplay = element.style.display || "";
            }

            element.style.display = "none";
            return;
        }

        element.style.display = element.dataset.oldDisplay ?? "";
    };

    window.getValue = function (selector, defaultValue = "")
    {
        const element = window.getElement(selector);
        if (!element) return defaultValue;

        if (!("value" in element)) {
            return defaultValue;
        }

        return element.value;
    };

    window.hasItems = function (value)
    {
        return Array.isArray(value) && value.length > 0;
    };

    window.click = function (selector, handler)
    {
        addDelegatedEvent("click", selector, handler);
    };

    window.clicked = window.click;

    window.clickData = function (name, handler)
    {
        const selector = getAttributeSelector(name);
        if (!selector) return;

        window.click(selector, (element, e) =>
        {
            const value = getAttributeValue(element, name);
            return handler(value, element, e);
        });
    };

    window.change = function (selector, handler)
    {
        addDelegatedEvent("change", selector, handler);
    };

    window.changeData = function (name, handler)
    {
        const selector = getAttributeSelector(name);
        if (!selector) return;

        window.change(selector, (element, e) =>
        {
            const value = getAttributeValue(element, name);
            return handler(value, element, e);
        });
    };

    window.edit = function (selector, handler)
    {
        addDelegatedEvent("input", selector, handler);
    };

    window.editData = function (name, handler)
    {
        const selector = getAttributeSelector(name);
        if (!selector) return;

        window.edit(selector, (element, e) =>
        {
            const value = getAttributeValue(element, name);
            return handler(value, element, e);
        });
    };

    window.keyDown = function (selector, handler)
    {
        addDelegatedEvent("keydown", selector, handler);
    };

    window.keyUp = function (selector, handler)
    {
        addDelegatedEvent("keyup", selector, handler);
    };

    window.keyDownData = function (name, handler)
    {
        const selector = getAttributeSelector(name);
        if (!selector) return;

        window.keyDown(selector, (element, e) =>
        {
            const value = getAttributeValue(element, name);
            return handler(value, element, e);
        });
    };

    window.keyUpData = function (name, handler)
    {
        const selector = getAttributeSelector(name);
        if (!selector) return;

        window.keyUp(selector, (element, e) =>
        {
            const value = getAttributeValue(element, name);
            return handler(value, element, e);
        });
    };

    window.Tabs = Tabs;

    onReady(() =>
    {
        window.getElements("[tabs]").forEach((container) =>
        {
            container._fwTabs = new Tabs(container);
        });
        Binding.init();
    });
})();