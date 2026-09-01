    </main>

    <footer class="site-footer">
        <div class="shell site-footer__inner">
            <span>Data and artwork by <a href="https://www.themoviedb.org/" target="_blank" rel="noopener noreferrer">TMDB</a>. This product uses the TMDB API but is not endorsed or certified by TMDB.</span>
            <nav class="site-footer__links">
                <a href="/">Home</a>
                <a href="https://github.com/theafricanboss/recommendedmovies.org" target="_blank" rel="noopener noreferrer">Contribute on GitHub</a>
                <a href="mailto:info@recommendedmovies.org">Contact</a>
            </nav>
        </div>
    </footer>

    <script>
    (function () {
        'use strict';

        // TMDB serves its artwork from a CDN that some networks block outright.
        // Marking a failed image hands the card back to its styled empty state,
        // so a blocked CDN degrades to a clean grid rather than broken icons.
        document.addEventListener('error', function (event) {
            var img = event.target;
            if (!img || img.tagName !== 'IMG') return;
            img.dataset.fallback = '1';
        }, true);

        // Header gains a border once the page is scrolled off the top.
        var header = document.querySelector('[data-site-header]');
        if (header) {
            var onScroll = function () {
                header.classList.toggle('site-header--scrolled', window.scrollY > 8);
            };
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });
        }

        // Long shelves render collapsed; everything is in the DOM already, so
        // this stays purely additive — with JS off you simply see every card.
        document.querySelectorAll('[data-collapse]').forEach(function (shelf) {
            var limit = parseInt(shelf.getAttribute('data-collapse'), 10);
            var cards = Array.prototype.slice.call(shelf.querySelectorAll('.card'));
            var button = shelf.querySelector('[data-more]');
            if (!button || cards.length <= limit) return;

            var expanded = false;
            var apply = function () {
                cards.forEach(function (card, i) {
                    card.hidden = !expanded && i >= limit;
                });
                button.textContent = expanded
                    ? 'Show less'
                    : 'Show all ' + cards.length;
                button.setAttribute('aria-expanded', String(expanded));
            };
            button.addEventListener('click', function () {
                expanded = !expanded;
                apply();
                if (!expanded) shelf.scrollIntoView({ block: 'start' });
            });
            apply();
        });

        // Live title filter across every shelf on the page.
        var filter = document.querySelector('[data-filter]');
        if (filter) {
            var shelves = Array.prototype.slice.call(document.querySelectorAll('.shelf'));
            filter.addEventListener('input', function () {
                var term = filter.value.trim().toLowerCase();
                shelves.forEach(function (shelf) {
                    var matches = 0;
                    shelf.querySelectorAll('.card').forEach(function (card) {
                        var hit = !term || (card.dataset.title || '').indexOf(term) !== -1;
                        card.hidden = !hit;
                        if (hit) matches++;
                    });
                    shelf.hidden = term !== '' && matches === 0;
                    var more = shelf.querySelector('[data-more]');
                    if (more) more.hidden = term !== '';
                });
                if (!term) {
                    // Restore the collapsed state the "show more" buttons manage.
                    document.querySelectorAll('[data-collapse]').forEach(function (shelf) {
                        var button = shelf.querySelector('[data-more]');
                        if (button && button.getAttribute('aria-expanded') === 'false') {
                            var limit = parseInt(shelf.getAttribute('data-collapse'), 10);
                            shelf.querySelectorAll('.card').forEach(function (card, i) {
                                card.hidden = i >= limit;
                            });
                        }
                    });
                }
            });
        }

        // Highlight the shelf currently in view in the jump nav.
        var links = Array.prototype.slice.call(document.querySelectorAll('.subnav__link'));
        if (links.length && 'IntersectionObserver' in window) {
            var byId = {};
            links.forEach(function (link) { byId[link.getAttribute('href').slice(1)] = link; });
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    links.forEach(function (l) { l.classList.remove('is-active'); });
                    var link = byId[entry.target.id];
                    if (link) link.classList.add('is-active');
                });
            }, { rootMargin: '-140px 0px -70% 0px' });
            Object.keys(byId).forEach(function (id) {
                var section = document.getElementById(id);
                if (section) observer.observe(section);
            });
        }
    })();
    </script>
</body>

</html>
