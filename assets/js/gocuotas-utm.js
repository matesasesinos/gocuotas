(function () {
  var params = new URLSearchParams(window.location.search);
  [
    "utm_source",
    "utm_medium",
    "utm_campaign",
    "utm_term",
    "utm_content",
  ].forEach(function (k) {
    if (params.get(k)) {
      document.cookie =
        k +
        "=" +
        encodeURIComponent(params.get(k)) +
        "; path=/; max-age=" +
        7 * 24 * 60 * 60;
    }
  });
})();
