(function () {
  function buildUrl(url, params) {
    let finalUrl = url;

    if (url.startsWith("api::")) {
      const apiName = url.substring(5);
      finalUrl = window.location.pathname + "?api=" + encodeURIComponent(apiName);
    }

    const parsed = new URL(finalUrl, window.location.origin);

    if (params) {
      for (const key in params) {
        if (params[key] === undefined || params[key] === null) continue;
        parsed.searchParams.set(key, params[key]);
      }
    }

    return parsed.toString();
  }

  async function readJson(response) {
    const text = await response.text();

    try {
      return JSON.parse(text);
    } catch {
      throw new Error(text || "Nieprawidłowa odpowiedź serwera.");
    }
  }

  window.send = async function (url, data) {
    const response = await fetch(buildUrl(url), {
      method: "POST",
      headers: {
        "Content-Type": "application/json; charset=utf-8"
      },
      body: JSON.stringify(data ?? {})
    });

    const json = await readJson(response);

    if (json.ok === false) {
      throw new Error(json.error?.message || "Wystąpił błąd.");
    }

    return json.data;
  };

  window.get = async function (url, params) {
    const response = await fetch(buildUrl(url, params));
    const json = await readJson(response);

    if (json.ok === false) {
      throw new Error(json.error?.message || "Wystąpił błąd.");
    }

    return json.data;
  };

  window.getRaw = async function (url, params) {
    const response = await fetch(buildUrl(url, params));
    return await response.text();
  };
})();

