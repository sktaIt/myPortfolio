/* Public behaviour only: theme toggle, mobile nav, scroll state, scrollspy.
   Nothing here knows the admin exists. */
(function () {
  'use strict';

  var root = document.documentElement;
  var STORAGE_KEY = 'portfolio-theme';

  /* Theme — a stored choice beats the default baked in by index.php. */
  try {
    var stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'light' || stored === 'dark') {
      root.setAttribute('data-theme', stored);
    }
  } catch (err) { /* private mode: fall back to the server default */ }

  var toggle = document.getElementById('theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem(STORAGE_KEY, next); } catch (err) { /* ignore */ }
    });
  }

  /* Mobile navigation */
  var navToggle = document.getElementById('nav-toggle');
  var nav = document.querySelector('.site-nav');
  if (navToggle && nav) {
    navToggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (event) {
      if (event.target.tagName === 'A') {
        nav.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* Border on the header once the page has scrolled */
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* Scrollspy: highlight the nav link for the section in view */
  var sections = Array.prototype.slice.call(document.querySelectorAll('main section[id]'));
  var links = {};
  Array.prototype.forEach.call(document.querySelectorAll('.site-nav a'), function (link) {
    links[link.getAttribute('href')] = link;
  });

  if (sections.length && 'IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        var link = links['#' + entry.target.id];
        if (link) { link.classList.toggle('is-active', entry.isIntersecting); }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });

    sections.forEach(function (section) { observer.observe(section); });
  }
})();
