(function () {
  window.ModalFieldType = {
    Text: "text",
    Number: "number",
    Email: "email",
    None: "none"
  };

  window.ModalButtonType = {
    Submit: "submit",
    Close: "close",
    None: "none"
  };

  function ModalBase() {
    this.kind = "base";
    this.title = "";
    this.text = "";
    this.closeX = true;
    this.closeOnBackdrop = false;
    this.fields = [];
    this.buttons = [];
  }

  ModalBase.prototype.show = function (callback) {
    return Modal.show(this, callback);
  };

  window.ModalInfo = function (title, text, buttons) {
    const m = new ModalBase();
    m.kind = "info";
    m.title = title || "";
    m.text = text || "";
    m.fields = [];
    m.buttons = Array.isArray(buttons) && buttons.length
      ? buttons
      : [Button.close("OK", true, "btn-primary")];
    return m;
  };

  window.ModalForm = function (title, fields, buttons) {
    const m = new ModalBase();
    m.kind = "form";
    m.title = title || "";
    m.fields = Array.isArray(fields) ? fields : [];
    m.buttons = Array.isArray(buttons) ? buttons : [];
    return m;
  };

  window.ModalConfirm = function (title, text, buttons) {
    const m = new ModalBase();
    m.kind = "confirm";
    m.title = title || "";
    m.text = text || "";
    m.fields = [];
    m.buttons = Array.isArray(buttons) && buttons.length
      ? buttons
      : [
          Button.close("Nie", false, "btn-secondary"),
          Button.close("Tak", true, "btn-danger")
        ];
    return m;
  };

  window.ModalField = function (id, label, type, placeholder, validation) {
    this.id = id ?? null;
    this.label = label ?? "";
    this.type = type ?? ModalFieldType.Text;
    this.placeholder = placeholder ?? "";
    this.text = "";
    this.cssClass = "";
    this.validation = Array.isArray(validation) ? validation : [];
  };

  window.ModalButton = function (text, value, type, cssClass, callback) {
    this.text = text ?? "OK";
    this.value = value;
    this.type = type ?? null;
    this.cssClass = cssClass ?? "btn-primary";
    this.callback = callback ?? null;
  };

  window.Field = {
    text: function (id, label, placeholder, validation) {
      return new ModalField(id, label, ModalFieldType.Text, placeholder, validation);
    },
    number: function (id, label, placeholder, validation) {
      return new ModalField(id, label, ModalFieldType.Number, placeholder, validation);
    },
    email: function (id, label, placeholder, validation) {
      return new ModalField(id, label, ModalFieldType.Email, placeholder, validation);
    },
    none: function (text, cssClass) {
      const f = new ModalField(null, "", ModalFieldType.None, "", []);
      f.text = text || "";
      f.cssClass = cssClass || "text-muted";
      return f;
    }
  };

  window.Button = {
    button: function (text, value, cssClass, callback) {
      return new ModalButton(text, value, null, cssClass, callback);
    },
    submit: function (text, cssClass, callback) {
      return new ModalButton(text, null, ModalButtonType.Submit, cssClass, callback);
    },
    close: function (text, value, cssClass, callback) {
      return new ModalButton(text, value, ModalButtonType.Close, cssClass, callback);
    },
    none: function (text, cssClass, callback) {
      return new ModalButton(text, null, ModalButtonType.None, cssClass, callback);
    }
  };

  function el(tag, cls, html) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html !== undefined) e.innerHTML = html;
    return e;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function clearFieldError(wrapper, input) {
    if (input) input.classList.remove("is-invalid");

    const errorEl = wrapper.querySelector(".fw-field-error");
    if (errorEl) {
      errorEl.textContent = "";
      errorEl.style.display = "none";
    }
  }

  function showFieldError(wrapper, input, message) {
    if (input) input.classList.add("is-invalid");

    const errorEl = wrapper.querySelector(".fw-field-error");
    if (errorEl) {
      errorEl.textContent = message || "";
      errorEl.style.display = "block";
    }
  }

  function normalizeValidationRule(rule) {
    if (Array.isArray(rule)) {
      return {
        regex: rule[0] ?? "",
        message: rule[1] ?? "",
        targetValue: rule.length >= 3 ? !!rule[2] : true
      };
    }

    if (rule && typeof rule === "object") {
      return {
        regex: rule.regex ?? "",
        message: rule.message ?? "",
        targetValue: rule.targetValue !== undefined ? !!rule.targetValue : true
      };
    }

    return {
      regex: "",
      message: "",
      targetValue: true
    };
  }

  function buildRegExp(value) {
    if (value instanceof RegExp) return value;
    return new RegExp(value);
  }

  function validateField(field, value) {
    let rules = Array.isArray(field.validation) ? field.validation : [];

    if (
      rules.length > 0 &&
      !Array.isArray(rules[0]) &&
      typeof rules[0] !== "object"
    ) {
      rules = [rules];
    }

    const errors = [];
    const stringValue = String(value ?? "").trim();

    for (const rawRule of rules) {
      const rule = normalizeValidationRule(rawRule);

      // specjalny przypadek:
      // [true, "To pole nie może być puste"]
      if (rule.regex === true) {
        if (stringValue === "") {
          errors.push(rule.message || "To pole nie może być puste.");
        }
        continue;
      }

      if (!rule.regex) continue;

      const regex = buildRegExp(rule.regex);
      const matched = regex.test(String(value ?? ""));
      const shouldMatch = rule.targetValue !== false;

      if (shouldMatch && !matched) {
        errors.push(rule.message || "Pole ma nieprawidłową wartość.");
      }

      if (!shouldMatch && matched) {
        errors.push(rule.message || "Pole ma nieprawidłową wartość.");
      }
    }

    return errors;
  }

  function collectFormData(box) {
    const data = {};

    box.querySelectorAll("[data-fw-field-id]").forEach(function (input) {
      const id = input.dataset.fwFieldId;
      data[id] = input.value;
    });

    return data;
  }

  function validateForm(box, modal) {
    let valid = true;

    modal.fields.forEach(function (field) {
      if (field.type === ModalFieldType.None) return;
      if (!field.id) return;

      const wrapper = box.querySelector('[data-fw-field-wrapper="' + field.id + '"]');
      const input = box.querySelector('[data-fw-field-id="' + field.id + '"]');

      if (!wrapper || !input) return;

      clearFieldError(wrapper, input);

      const errors = validateField(field, input.value);

      if (errors.length > 0) {
        valid = false;
        showFieldError(wrapper, input, errors[0]);
      }
    });

    return valid;
  }

  function build(modal) {
    const overlay = el("div", "fw-modal-overlay");
    overlay.style.cssText =
      "position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:9999;padding:16px;";

    const box = el("div", "fw-modal-box bg-white rounded shadow");
    box.style.cssText = "width:100%;max-width:560px;";
    overlay.appendChild(box);

    const head = el("div", "p-3 border-bottom d-flex align-items-center justify-content-between");
    head.appendChild(el("div", "fw-modal-title fw-bold", escapeHtml(modal.title || "")));

    if (modal.closeX) {
      const closeButton = el("button", "btn btn-sm btn-outline-secondary", "&times;");
      closeButton.type = "button";
      closeButton.onclick = function (e) {
        e.preventDefault();
        e.stopPropagation();
        close(null);
      };
      head.appendChild(closeButton);
    }

    box.appendChild(head);

    const body = el("div", "p-3");

    if (modal.text) {
      body.appendChild(el("div", "mb-3", escapeHtml(modal.text)));
    }

    if (modal.kind === "form") {
      modal.fields.forEach(function (field) {
        if (field.type === ModalFieldType.None) {
          body.appendChild(
            el("div", "mb-3 " + (field.cssClass || "text-muted"), escapeHtml(field.text || ""))
          );
          return;
        }

        const wrapper = el("div", "mb-3");
        wrapper.setAttribute("data-fw-field-wrapper", field.id || "");

        wrapper.appendChild(el("label", "form-label", escapeHtml(field.label || "")));

        const input = el("input", "form-control");
        input.type = field.type === ModalFieldType.Number
          ? "number"
          : field.type === ModalFieldType.Email
          ? "email"
          : "text";

        input.dataset.fwFieldId = field.id || "";
        input.placeholder = field.placeholder || "";

        input.addEventListener("input", function () {
          clearFieldError(wrapper, input);
        });

        wrapper.appendChild(input);

        const errorEl = el("div", "fw-field-error invalid-feedback");
        errorEl.style.display = "none";
        wrapper.appendChild(errorEl);

        body.appendChild(wrapper);
      });
    }

    box.appendChild(body);

    const foot = el("div", "p-3 border-top d-flex gap-2 justify-content-end");

    (modal.buttons || []).forEach(function (button) {
      const btn = el(
        "button",
        "btn " + (button.cssClass || "btn-primary"),
        escapeHtml(button.text || "OK")
      );

      btn.type = "button";

      btn.onclick = function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (typeof button.callback === "function") {
          button.callback();
        }

        if (button.type === ModalButtonType.None) {
          return false;
        }

        if (button.type === ModalButtonType.Submit && modal.kind === "form") {
          const isValid = validateForm(box, modal);

          if (!isValid) {
            return false;
          }

          const data = collectFormData(box);
          close(data);
          return false;
        }

        const value = button.value !== undefined ? button.value : true;
        close(value);
        return false;
      };

      foot.appendChild(btn);
    });

    box.appendChild(foot);

    if (modal.closeOnBackdrop) {
      overlay.addEventListener("click", function (e) {
        if (e.target === overlay) {
          close(null);
        }
      });
    }

    function close(result) {
      if (document.body.contains(overlay)) {
        document.body.removeChild(overlay);
      }
      done(result);
    }

    let done = function () {};
    return {
      overlay: overlay,
      setDone: function (fn) {
        done = fn;
      }
    };
  }

  window.Modal = {
    show: function (modal, callback) {
      return new Promise(function (resolve) {
        const ui = build(modal);

        ui.setDone(function (result) {
          if (callback) callback(result);
          resolve(result);
        });

        document.body.appendChild(ui.overlay);
      });
    },

    error: function (text) {
      return Modal.show(new ModalInfo("Błąd", text || "Wystąpił błąd."));
    }
  };
})();