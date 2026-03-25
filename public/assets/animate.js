(function () {
  const getElements = window.getElements;

  const DEFAULT_TIME = 250;
  const DEFAULT_EASING = "linear";

  const PRESETS = {
    "fade-in": [
      { opacity: 0 },
      { opacity: 1 }
    ],
    "fade-up": [
      { opacity: 0, transform: "translateY(32px)" },
      { opacity: 1, transform: "translateY(0px)" }
    ],
    "fade-down": [
      { opacity: 0, transform: "translateY(-20px)" },
      { opacity: 1, transform: "translateY(0px)" }
    ],
    "fade-left": [
      { opacity: 0, transform: "translateX(20px)" },
      { opacity: 1, transform: "translateX(0px)" }
    ],
    "fade-right": [
      { opacity: 0, transform: "translateX(-28px)" },
      { opacity: 1, transform: "translateX(0px)" }
    ],
    "zoom-in": [
      { opacity: 0, transform: "scale(1.2)" },
      { opacity: 1, transform: "scale(1)" }
    ],
    "zoom-out": [
      { opacity: 0, transform: "scale(1.08)" },
      { opacity: 1, transform: "scale(1)" }
    ],
    "pop": [
      { opacity: 0, transform: "scale(0.86)" },
      { opacity: 1, transform: "scale(1.1)" },
      { opacity: 1, transform: "scale(1)" }
    ],
    "scale-out": [
      { opacity: 0, transform: "scale(0)" },
      { opacity: 1, transform: "scale(1)" }
    ],
    "slide-up": [
      { transform: "translateY(20px)" },
      { transform: "translateY(0px)" }
    ],
    "slide-down": [
      { transform: "translateY(-20px)" },
      { transform: "translateY(0px)" }
    ],
    "typewriter": [
      { opacity: 1, __typewriterProgress: 0 },
      { opacity: 1, __typewriterProgress: 1 }
    ],
    "reveal-down": [
      { opacity: 1, __revealDownProgress: 0 },
      { opacity: 1, __revealDownProgress: 1 }
    ]
  };

  const stateMap = new WeakMap();

  function parseTime(value, fallback) {
    if (value === null || value === undefined || value === "") {
      return fallback;
    }

    const text = String(value).trim().toLowerCase();

    if (text.endsWith("ms")) {
      const ms = parseFloat(text.slice(0, -2));
      return Number.isFinite(ms) ? ms : fallback;
    }

    if (text.endsWith("s")) {
      const sec = parseFloat(text.slice(0, -1));
      return Number.isFinite(sec) ? sec * 1000 : fallback;
    }

    const number = parseFloat(text);
    return Number.isFinite(number) ? number : fallback;
  }

  function parseTimeList(value, fallback) {
    return String(value === null || value === undefined ? "" : value)
      .split(",")
      .map(function (item) {
        return item.trim();
      })
      .filter(function (item, index, arr) {
        return item !== "" || arr.length === 1;
      })
      .map(function (item) {
        return parseTime(item, fallback);
      });
  }

  function getIndexedTime(list, index, fallback) {
    if (!Array.isArray(list) || list.length === 0) {
      return fallback;
    }

    const safeIndex = Math.max(0, Math.min(index, list.length - 1));
    const value = list[safeIndex];
    return Number.isFinite(value) ? value : fallback;
  }

  function parseBool(value, fallback) {
    if (value === null || value === undefined || value === "") {
      return fallback;
    }

    const text = String(value).trim().toLowerCase();

    if (text === "true") return true;
    if (text === "false") return false;

    return fallback;
  }

  function normalizePresetList(value) {
    return String(value || "")
      .split(",")
      .map(function (item) {
        return item.trim();
      })
      .filter(Boolean);
  }

  function invertMarginValue(value) {
    if (value === null || value === undefined || value === "") {
      return "0px";
    }

    return String(value)
      .trim()
      .split(/\s+/)
      .map(function (part) {
        if (part === "0" || part === "0px" || part === "0%") {
          return part;
        }

        if (part.startsWith("-")) {
          return part.slice(1);
        }

        if (/^[0-9.]/.test(part) || part.startsWith("+")) {
          return "-" + part.replace(/^\+/, "");
        }

        return part;
      })
      .join(" ");
  }

  function getTriggerDefaultOnce(trigger) {
    return trigger === "view";
  }

  function splitTypes(type) {
    return normalizePresetList(type);
  }

  function hasType(type, name) {
    return splitTypes(type).includes(name);
  }

  function getKeyframes(name) {
    return PRESETS[name] || null;
  }

  function ensureState(element) {
    if (!stateMap.has(element)) {
      stateMap.set(element, {
        initialized: false,
        running: [],
        direction: "start",
        done: false,
        observer: null,
        handlersBound: false,
        inView: null
      });
    }

    return stateMap.get(element);
  }

  function cancelRunning(state) {
    if (!Array.isArray(state.running)) {
      state.running = [];
      return;
    }

    state.running.forEach(function (animation) {
      if (animation && typeof animation.cancel === "function") {
        animation.cancel();
      }
    });

    state.running = [];
  }

  function childUsesOwnAnimation(child) {
    return child.hasAttribute("animation") || child.hasAttribute("children-animation");
  }

  function getChildrenTargets(element) {
    return Array.from(element.children).filter(function (child) {
      return !childUsesOwnAnimation(child);
    });
  }

  function getConfig(element) {
    const type = element.getAttribute("animation") || "";
    const childrenType = element.getAttribute("children-animation") || "";
    const trigger = (element.getAttribute("animation-trigger") || "default").toLowerCase();
    const margin = element.getAttribute("animation-margin") || "200px";
    const timeList = parseTimeList(element.getAttribute("animation-time"), DEFAULT_TIME);
    const delayList = parseTimeList(element.getAttribute("animation-delay"), 0);
    const time = getIndexedTime(timeList, 0, DEFAULT_TIME);
    const delay = getIndexedTime(delayList, 0, 0);
    const easing = element.getAttribute("animation-easing") || DEFAULT_EASING;
    const once = parseBool(element.getAttribute("animation-once"), getTriggerDefaultOnce(trigger));
    const back = parseBool(element.getAttribute("animation-back"), false);
    const childrenDelay = parseTime(element.getAttribute("children-animation-delay"), 0);

    return {
      type: type,
      childrenType: childrenType,
      trigger: trigger,
      margin: margin,
      time: time,
      delay: delay,
      easing: easing,
      once: once,
      back: back,
      childrenDelay: childrenDelay,
      timeList: timeList,
      delayList: delayList
    };
  }

  function getTypewriterTarget(element) {
    if (element.__animationTypewriterTarget) {
      return element.__animationTypewriterTarget;
    }

    let target = element;

    if (
      element.childElementCount === 1 &&
      (element.textContent || "").trim() === ((element.firstElementChild && element.firstElementChild.textContent) || "").trim()
    ) {
      target = element.firstElementChild;
    }

    element.__animationTypewriterTarget = target;
    return target;
  }

  function prepareTypewriterElement(element) {
    const target = getTypewriterTarget(element);

    if (target.__animationTypewriterOriginal === undefined) {
      target.__animationTypewriterOriginal = target.textContent || "";
          target.__animationTypewriterLines = target.__animationTypewriterOriginal.split("\n");
      target.__animationTypewriterLineLengths = target.__animationTypewriterLines.map(function (line) {
        return line.length;
      });
      target.__animationTypewriterTotalLength = target.__animationTypewriterLineLengths.reduce(function (sum, length) {
        return sum + length;
      }, 0);
    }

    target.style.opacity = "1";
    target.style.whiteSpace = "pre-wrap";
  }

  function prepareRevealDownElement(element) {
    element.style.willChange = "opacity, -webkit-mask-image, mask-image";
  }

  function preparePresetElement(element, type) {
    const types = splitTypes(type);

    if (types.includes("typewriter")) {
      prepareTypewriterElement(element);
    }

    if (types.includes("reveal-down")) {
      prepareRevealDownElement(element);
    }
  }

  function frameHasTypewriter(frame) {
    return frame && Object.prototype.hasOwnProperty.call(frame, "__typewriterProgress");
  }

  function frameHasRevealDown(frame) {
    return frame && Object.prototype.hasOwnProperty.call(frame, "__revealDownProgress");
  }

  function applyTypewriterProgress(element, progress) {
    const target = getTypewriterTarget(element);
    const original = target.__animationTypewriterOriginal !== undefined
      ? target.__animationTypewriterOriginal
      : (target.textContent || "");
    const lines = Array.isArray(target.__animationTypewriterLines)
      ? target.__animationTypewriterLines
      : original.split("\n");

    const lineLengths = Array.isArray(target.__animationTypewriterLineLengths)
      ? target.__animationTypewriterLineLengths
      : lines.map(function (line) { return line.length; });

    const totalLength = Number.isFinite(target.__animationTypewriterTotalLength)
      ? target.__animationTypewriterTotalLength
      : lineLengths.reduce(function (sum, length) { return sum + length; }, 0);

    const safeProgress = Math.max(0, Math.min(1, progress));
    const length = Math.round(original.length * safeProgress);
        let remaining = Math.round(totalLength * safeProgress);

    const renderedLines = lines.map(function (line, index) {
      const lineLength = lineLengths[index] || 0;

      if (remaining <= 0) {
        return "";
      }

      if (remaining >= lineLength) {
        remaining -= lineLength;
        return line;
      }

      const partial = line.slice(0, remaining);
      remaining = 0;
      return partial;
    });

    target.textContent = renderedLines.join("\n");
  }

  function applyRevealDownProgress(element, progress) {
    const safeProgress = Math.max(0, Math.min(1, progress));
    const edge = safeProgress * 100;
    const fadeSize = 14;
    const solidEnd = Math.max(0, edge - fadeSize);
    const gradient =
      "linear-gradient(to bottom, " +
      "rgba(0,0,0,1) 0%, " +
      "rgba(0,0,0,1) " + solidEnd + "%, " +
      "rgba(0,0,0,0.5) " + edge + "%, " +
      "rgba(0,0,0,0) " + Math.min(100, edge + fadeSize) + "%, " +
      "rgba(0,0,0,0) 100%)";

    element.style.maskImage = gradient;
    element.style.WebkitMaskImage = gradient;
    element.style.maskRepeat = "no-repeat";
    element.style.WebkitMaskRepeat = "no-repeat";
    element.style.maskSize = "100% 100%";
    element.style.WebkitMaskSize = "100% 100%";
  }

  function clearRevealDownMask(element) {
    element.style.maskImage = "none";
    element.style.WebkitMaskImage = "none";
    element.style.maskRepeat = "";
    element.style.WebkitMaskRepeat = "";
    element.style.maskSize = "";
    element.style.WebkitMaskSize = "";
  }

  function parseTransform(transform) {
    const result = {
      translateX: null,
      translateY: null,
      scale: null
    };

    if (!transform) {
      return result;
    }

    const tx = /translateX\((-?[0-9.]+)px\)/.exec(transform);
    const ty = /translateY\((-?[0-9.]+)px\)/.exec(transform);
    const sc = /scale\((-?[0-9.]+)\)/.exec(transform);

    if (tx) result.translateX = parseFloat(tx[1]);
    if (ty) result.translateY = parseFloat(ty[1]);
    if (sc) result.scale = parseFloat(sc[1]);

    return result;
  }

  function buildTransform(transformObj) {
    const parts = [];

    if (transformObj.translateX !== null) {
      parts.push("translateX(" + transformObj.translateX + "px)");
    }
    if (transformObj.translateY !== null) {
      parts.push("translateY(" + transformObj.translateY + "px)");
    }
    if (transformObj.scale !== null) {
      parts.push("scale(" + transformObj.scale + ")");
    }

    return parts.length ? parts.join(" ") : "none";
  }

  function parseClipPath(value) {
    const match = /inset\(([-0-9.]+)%\s+([-0-9.]+)%\s+([-0-9.]+)%\s+([-0-9.]+)%\)/.exec(value || "");
    if (!match) {
      return null;
    }

    return {
      top: parseFloat(match[1]),
      right: parseFloat(match[2]),
      bottom: parseFloat(match[3]),
      left: parseFloat(match[4])
    };
  }

  function buildClipPath(obj) {
    return "inset(" + obj.top + "% " + obj.right + "% " + obj.bottom + "% " + obj.left + "%)";
  }

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function easeProgress(progress, easing) {
    const p = Math.max(0, Math.min(1, progress));

    switch ((easing || "linear").toLowerCase()) {
      case "ease-in":
        return p * p;
      case "ease-out":
        return 1 - Math.pow(1 - p, 2);
      case "ease-in-out":
        return p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;
      case "ease":
        return p < 0.5
          ? 4 * p * p * p
          : 1 - Math.pow(-2 * p + 2, 3) / 2;
      default:
        return p;
    }
  }

  function interpolateFrames(fromFrame, toFrame, t) {
    const result = {};
    const keys = new Set(Object.keys(fromFrame || {}).concat(Object.keys(toFrame || {})));

    keys.forEach(function (key) {
      if (key === "__typewriterProgress" || key === "__revealDownProgress") {
        const fromValue = fromFrame && fromFrame[key] !== undefined ? fromFrame[key] : 0;
        const toValue = toFrame && toFrame[key] !== undefined ? toFrame[key] : 1;
        result[key] = lerp(fromValue, toValue, t);
        return;
      }

      if (key === "opacity") {
        const fromValue = fromFrame && fromFrame.opacity !== undefined ? parseFloat(fromFrame.opacity) : 1;
        const toValue = toFrame && toFrame.opacity !== undefined ? parseFloat(toFrame.opacity) : fromValue;
        result.opacity = lerp(fromValue, toValue, t);
        return;
      }

      if (key === "transform") {
        const fromTransform = parseTransform(fromFrame && fromFrame.transform);
        const toTransform = parseTransform(toFrame && toFrame.transform);
        const merged = {
          translateX: null,
          translateY: null,
          scale: null
        };

        ["translateX", "translateY", "scale"].forEach(function (name) {
          const fromValue = fromTransform[name] !== null ? fromTransform[name] : (name === "scale" ? 1 : 0);
          const toValue = toTransform[name] !== null ? toTransform[name] : fromValue;
          if (fromTransform[name] !== null || toTransform[name] !== null) {
            merged[name] = lerp(fromValue, toValue, t);
          }
        });

        result.transform = buildTransform(merged);
        return;
      }

      if (key === "clipPath" || key === "WebkitClipPath") {
        const fromClip = parseClipPath(fromFrame && (fromFrame.clipPath || fromFrame.WebkitClipPath));
        const toClip = parseClipPath(toFrame && (toFrame.clipPath || toFrame.WebkitClipPath));

        if (fromClip && toClip) {
          const clip = {
            top: lerp(fromClip.top, toClip.top, t),
            right: lerp(fromClip.right, toClip.right, t),
            bottom: lerp(fromClip.bottom, toClip.bottom, t),
            left: lerp(fromClip.left, toClip.left, t)
          };
          result.clipPath = buildClipPath(clip);
          result.WebkitClipPath = result.clipPath;
        }
        return;
      }

      if (toFrame && toFrame[key] !== undefined) {
        result[key] = toFrame[key];
      } else if (fromFrame && fromFrame[key] !== undefined) {
        result[key] = fromFrame[key];
      }
    });

    return result;
  }

  function getPresetFrameAtProgress(type, progress, direction) {
    const keyframes = getKeyframes(type);
    if (!keyframes || keyframes.length === 0) {
      return null;
    }

    const frames = direction === "back" ? keyframes.slice().reverse() : keyframes;

    if (frames.length === 1) {
      return frames[0];
    }

    const safe = Math.max(0, Math.min(1, progress));
    const scaled = safe * (frames.length - 1);
    const index = Math.floor(scaled);
    const nextIndex = Math.min(frames.length - 1, index + 1);
    const localT = scaled - index;

    return interpolateFrames(frames[index], frames[nextIndex], localT);
  }

  function mergePresetFrames(frameList) {
    const result = {};

    for (let i = frameList.length - 1; i >= 0; i--) {
      const frame = frameList[i];
      if (!frame) continue;

      Object.keys(frame).forEach(function (key) {
        result[key] = frame[key];
      });
    }

    return result;
  }

  function applyComputedFrame(element, frame) {
    if (!frame) return;

    Object.keys(frame).forEach(function (key) {
      if (key === "__typewriterProgress" || key === "__revealDownProgress") {
        return;
      }
      element.style[key] = frame[key];
    });

    if (frame.clipPath !== undefined) {
      element.style.WebkitClipPath = frame.clipPath;
    }

    if (frameHasTypewriter(frame)) {
      applyTypewriterProgress(element, frame.__typewriterProgress);
    }

    if (frameHasRevealDown(frame)) {
      applyRevealDownProgress(element, frame.__revealDownProgress);
    }
  }

  function buildSingleAnimationConfig(element) {
    const config = getConfig(element);
    return {
      type: config.type,
      time: config.time,
      delay: config.delay,
      easing: config.easing,
      step: 0,
      once: config.once,
      back: config.back,
      trigger: config.trigger,
      timeList: config.timeList,
      delayList: config.delayList
    };
  }

  function buildChildrenAnimationConfig(element) {
    const config = getConfig(element);
    return {
      type: config.childrenType,
      time: config.time,
      delay: config.delay,
      easing: config.easing,
      step: config.childrenDelay,
      once: config.once,
      back: config.back,
      trigger: config.trigger,
      timeList: config.timeList,
      delayList: config.delayList
    };
  }

  function animateOne(element, config, direction, extraDelay, onEnd) {
    const state = ensureState(element);
    const types = splitTypes(config.type);

    if (types.length === 0) {
      if (typeof onEnd === "function") {
        onEnd();
      }
      return;
    }

    const segments = types.map(function (type, index) {
      const time = getIndexedTime(config.timeList, index, config.time);
      const delay = getIndexedTime(config.delayList, index, config.delay) + (extraDelay || 0);
      return {
        type: type,
        time: Math.max(1, time),
        delay: Math.max(0, delay)
      };
    });

    const totalTime = segments.reduce(function (max, segment) {
      return Math.max(max, segment.delay + segment.time);
    }, 0);

    let rafId = 0;
    let cancelled = false;
    const startTime = performance.now();

    const animationRecord = {
      cancel: function () {
        cancelled = true;
        if (rafId) {
          cancelAnimationFrame(rafId);
        }
      }
    };

    state.running.push(animationRecord);

    function tick(now) {
      if (cancelled) {
        state.running = state.running.filter(function (item) {
          return item !== animationRecord;
        });
        return;
      }

      const elapsed = now - startTime;
      const frameList = segments.map(function (segment) {
        const local = Math.max(0, Math.min(1, (elapsed - segment.delay) / segment.time));
        const eased = easeProgress(local, config.easing);
        return getPresetFrameAtProgress(segment.type, eased, direction);
      });

      const merged = mergePresetFrames(frameList);
      applyComputedFrame(element, merged);

      if (elapsed >= totalTime) {
        state.running = state.running.filter(function (item) {
          return item !== animationRecord;
        });

        const finalFrameList = segments.map(function (segment) {
          return getPresetFrameAtProgress(segment.type, direction === "back" ? 0 : 1, "play");
        });
        const finalMerged = mergePresetFrames(finalFrameList);
        applyComputedFrame(element, finalMerged);

        if (!hasType(config.type, "reveal-down")) {
          clearRevealDownMask(element);
        } else if (direction === "play") {
          clearRevealDownMask(element);
        }

        if (typeof onEnd === "function") {
          onEnd();
        }
        return;
      }

      rafId = requestAnimationFrame(tick);
    }

    rafId = requestAnimationFrame(tick);
  }

  function animateTargets(targets, config, direction, callback) {
    if (!targets.length) {
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    const orderedTargets = direction === "back" ? targets.slice().reverse() : targets.slice();
    let finished = 0;

    function done() {
      finished++;
      if (finished >= orderedTargets.length && typeof callback === "function") {
        callback();
      }
    }

    orderedTargets.forEach(function (target, index) {
      preparePresetElement(target, config.type);
      animateOne(target, config, direction, (config.step || 0) * index, done);
    });
  }

  function resetElement(element) {
    const config = getConfig(element);
    const state = ensureState(element);

    cancelRunning(state);

    if (config.type) {
      preparePresetElement(element, config.type);
      const frameList = splitTypes(config.type).map(function (type) {
        return getPresetFrameAtProgress(type, 0, "play");
      });
      applyComputedFrame(element, mergePresetFrames(frameList));
      if (hasType(config.type, "typewriter")) {
        applyTypewriterProgress(element, 0);
      }
      if (hasType(config.type, "reveal-down")) {
        applyRevealDownProgress(element, 0);
      }
    }

    if (config.childrenType) {
      getChildrenTargets(element).forEach(function (child) {
        preparePresetElement(child, config.childrenType);
        const frameList = splitTypes(config.childrenType).map(function (type) {
          return getPresetFrameAtProgress(type, 0, "play");
        });
        applyComputedFrame(child, mergePresetFrames(frameList));
        if (hasType(config.childrenType, "typewriter")) {
          applyTypewriterProgress(child, 0);
        }
        if (hasType(config.childrenType, "reveal-down")) {
          applyRevealDownProgress(child, 0);
        }
      });
    }

    state.done = false;
    state.direction = "start";
    state.initialized = true;
  }

  function playElement(element, callback, force) {
    const config = getConfig(element);
    const state = ensureState(element);

    if (!state.initialized) {
      setInitialState(element);
    }

    if (state.direction === "play" && !force) {
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    if (config.once && state.done && !force) {
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    if (state.direction !== "back") {
      resetElement(element);
    } else {
      cancelRunning(state);
    }

    state.direction = "play";
    state.done = false;

    const jobs = [];

    if (config.type) {
      jobs.push(function (done) {
        animateTargets([element], buildSingleAnimationConfig(element), "play", done);
      });
    }

    if (config.childrenType) {
      jobs.push(function (done) {
        animateTargets(getChildrenTargets(element), buildChildrenAnimationConfig(element), "play", done);
      });
    }

    if (!jobs.length) {
      state.direction = "end";
      state.done = true;
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    let finished = 0;

    jobs.forEach(function (job) {
      job(function () {
        finished++;
        if (finished >= jobs.length) {
          state.done = true;
          state.direction = "end";
          if (typeof callback === "function") {
            callback();
          }
        }
      });
    });
  }

  function backElement(element, callback) {
    const config = getConfig(element);
    const state = ensureState(element);

    if (!state.initialized) {
      setInitialState(element);
    }

    if (!config.back) {
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    if (state.direction === "back") {
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    cancelRunning(state);

    state.done = false;
    state.direction = "back";

    const jobs = [];

    if (config.childrenType) {
      jobs.push(function (done) {
        animateTargets(getChildrenTargets(element), buildChildrenAnimationConfig(element), "back", done);
      });
    }

    if (config.type) {
      jobs.push(function (done) {
        animateTargets([element], buildSingleAnimationConfig(element), "back", done);
      });
    }

    if (!jobs.length) {
      state.direction = "start";
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    let finished = 0;

    jobs.forEach(function (job) {
      job(function () {
        finished++;
        if (finished >= jobs.length) {
          state.done = false;
          state.direction = "start";
          if (typeof callback === "function") {
            callback();
          }
        }
      });
    });
  }

  function setInitialState(element) {
    resetElement(element);
  }

  function bindTrigger(element) {
    const config = getConfig(element);
    const state = ensureState(element);

    if (state.handlersBound) {
      return;
    }

    if (config.trigger === "default") {
      requestAnimationFrame(function () {
        playElement(element);
      });
      state.handlersBound = true;
      return;
    }

    if (config.trigger === "click") {
      element.addEventListener("click", function () {
        playElement(element, null, true);
      });
      state.handlersBound = true;
      return;
    }

    if (config.trigger === "hover") {
      element.addEventListener("mouseenter", function () {
        if (state.direction !== "end" && state.direction !== "play") {
          playElement(element, null, true);
        }
      });

      element.addEventListener("mouseleave", function () {
        if (getConfig(element).back && state.direction !== "start" && state.direction !== "back") {
          backElement(element);
        }
      });

      state.handlersBound = true;
      return;
    }

    if (config.trigger === "view") {
      const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          const isVisible = entry.isIntersecting;

          if (state.inView === isVisible) {
            return;
          }

          state.inView = isVisible;

          if (isVisible) {
            if (state.direction !== "end" && state.direction !== "play") {
              playElement(element, null, true);
            }
          } else if (getConfig(element).back) {
            if (state.direction !== "start" && state.direction !== "back") {
              backElement(element);
            }
          }
        });
      }, {
        root: null,
        rootMargin: invertMarginValue(config.margin),
        threshold: 0
      });

      observer.observe(element);
      state.observer = observer;
      state.handlersBound = true;
      return;
    }

    state.handlersBound = true;
  }

  function initElement(element) {
    const config = getConfig(element);

    if (!config.type && !config.childrenType) {
      return;
    }

    setInitialState(element);
    bindTrigger(element);
  }

  function runOnTargets(target, action, callback, force) {
    const elements = getElements(target);
    if (elements.length === 0) {
      if (typeof callback === "function") {
        callback();
      }
      return;
    }

    let finished = 0;

    function done() {
      finished++;
      if (finished >= elements.length && typeof callback === "function") {
        callback();
      }
    }

    elements.forEach(function (element) {
      initElement(element);

      if (action === "play") {
        playElement(element, done, !!force);
        return;
      }

      if (action === "back") {
        backElement(element, done);
        return;
      }

      if (action === "reset") {
        resetElement(element);
        done();
      }
    });
  }

  window.Animate = {
    play: function (target, callback) {
      runOnTargets(target, "play", callback, true);
    },
    back: function (target, callback) {
      runOnTargets(target, "back", callback, false);
    },
    reset: function (target) {
      runOnTargets(target, "reset", null, false);
    }
  };

  document.addEventListener("DOMContentLoaded", function () {
    const elements = getElements("[animation], [children-animation]");

    elements.forEach(function (element) {
      const config = getConfig(element);
      if (config.type || config.childrenType) {
        resetElement(element);
      }
    });

    requestAnimationFrame(function () {
      elements.forEach(function (element) {
        initElement(element);
      });
    });
  });
})();