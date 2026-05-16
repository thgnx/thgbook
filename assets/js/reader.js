/**
 * ThgBook Reader — handles EPUB (epub.js) and PDF (PDF.js)
 */
const BASE_URL = '/tools/ThgBook';

(function (cfg) {

    // =================== Settings ===================
    const STORAGE_KEY = 'thgbook_reader_settings';
    const THEMES = {
        light: { bg: '#f8f5ef', color: '#1a1612' },
        sepia: { bg: '#f4ecd8', color: '#2c1f0e' },
        gray:  { bg: '#2d2d2d', color: '#e0e0e0' },
        dark:  { bg: '#111111', color: '#e8e0d0' },
    };
    const DEFAULTS = { theme: 'light', fontFamily: 'Georgia, serif', fontSize: 18, lineHeight: 1.7, margin: 4 };
    let settings = { ...DEFAULTS };
    try { Object.assign(settings, JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}')); } catch (_) {}

    function saveSettings() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(settings)); } catch (_) {}
    }

    // =================== DOM refs ===================
    const progressFill = document.getElementById('progress-fill');
    const pageInfo     = document.getElementById('page-info');
    const chapterEl    = document.getElementById('reader-chapter');

    // =================== Progress save ===================
    let saveTimer = null;
    function scheduleProgressSave(data) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => saveProgress(data), 1500);
    }
    async function saveProgress(data) {
        try {
            await fetch(BASE_URL + '/api/update_progress.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ user_book_id: cfg.userBookId, ...data })
            });
        } catch (_) {}
    }

    function setProgressUI(pct) {
        const p = Math.min(100, Math.max(0, pct));
        if (progressFill) progressFill.style.width = p.toFixed(1) + '%';
    }

    // =================== Loading overlay ===================
    function hideLoading() {
        const el = document.getElementById('reader-loading');
        if (!el) return;
        el.classList.add('fade-out');
        setTimeout(() => el.remove(), 380);
    }

    // =================== Panel open/close ===================
    let closeTocPanel = () => {};

    function openPanel(panelId, backdropId) {
        document.getElementById(panelId)?.classList.add('open');
        document.getElementById(backdropId)?.classList.add('open');
    }
    function closePanel(panelId, backdropId) {
        document.getElementById(panelId)?.classList.remove('open');
        document.getElementById(backdropId)?.classList.remove('open');
    }
    function closeSettingsPanel() { closePanel('settings-panel', 'settings-backdrop'); }

    // Escape key closes any open panel
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeSettingsPanel(); closeTocPanel(); }
    });

    // =================== Settings panel UI ===================
    function applySettingsToRendition(rendition) {
        if (!rendition) return;
        const t = THEMES[settings.theme] || THEMES.light;
        rendition.themes.register('custom', {
            body: {
                'background':  t.bg,
                'color':       t.color,
                'font-family': settings.fontFamily,
                'font-size':   settings.fontSize + 'px',
                'line-height': String(settings.lineHeight),
                'padding':     '0 ' + settings.margin + '% !important',
            },
            'p, li': { 'margin-bottom': '0.75em' },
        });
        rendition.themes.select('custom');
    }

    function updateSettingsUI() {
        document.querySelectorAll('.theme-dot').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.theme === settings.theme);
        });
        const fontSel = document.getElementById('font-family-select');
        if (fontSel) fontSel.value = settings.fontFamily;
        const fsVal = document.getElementById('font-size-val');
        if (fsVal) fsVal.textContent = settings.fontSize + 'px';
        const lhVal = document.getElementById('line-height-val');
        if (lhVal) lhVal.textContent = settings.lineHeight.toFixed(1);
        const mVal = document.getElementById('margin-val');
        if (mVal) mVal.textContent = settings.margin + '%';
    }

    function setupStepper(decId, incId, valId, key, min, max, step, format, onApply) {
        const dec = document.getElementById(decId);
        const inc = document.getElementById(incId);
        const val = document.getElementById(valId);
        if (!dec || !inc || !val) return;
        dec.addEventListener('click', () => {
            const next = Math.round((settings[key] - step) * 1000) / 1000;
            if (next < min) return;
            settings[key] = next;
            val.textContent = format(settings[key]);
            saveSettings();
            onApply();
        });
        inc.addEventListener('click', () => {
            const next = Math.round((settings[key] + step) * 1000) / 1000;
            if (next > max) return;
            settings[key] = next;
            val.textContent = format(settings[key]);
            saveSettings();
            onApply();
        });
    }

    let epubRendition = null; // set inside initEpub, used by settings callbacks

    function initSettingsUI() {
        updateSettingsUI();

        // Settings panel open/close
        document.getElementById('settings-btn')?.addEventListener('click', () => {
            document.body.classList.remove('ui-hidden');
            openPanel('settings-panel', 'settings-backdrop');
        });
        document.getElementById('settings-backdrop')?.addEventListener('click', closeSettingsPanel);
        document.getElementById('settings-close')?.addEventListener('click', closeSettingsPanel);

        // Theme dots
        document.querySelectorAll('.theme-dot').forEach(btn => {
            btn.addEventListener('click', () => {
                settings.theme = btn.dataset.theme;
                saveSettings();
                applySettingsToRendition(epubRendition);
                updateSettingsUI();
            });
        });

        // Font family (EPUB only — element absent for PDF)
        document.getElementById('font-family-select')?.addEventListener('change', e => {
            settings.fontFamily = e.target.value;
            saveSettings();
            applySettingsToRendition(epubRendition);
        });

        // Steppers (EPUB only)
        setupStepper('font-size-dec', 'font-size-inc', 'font-size-val',
            'fontSize', 14, 28, 2, v => v + 'px',
            () => applySettingsToRendition(epubRendition));
        setupStepper('line-height-dec', 'line-height-inc', 'line-height-val',
            'lineHeight', 1.4, 2.2, 0.1, v => v.toFixed(1),
            () => applySettingsToRendition(epubRendition));
        setupStepper('margin-dec', 'margin-inc', 'margin-val',
            'margin', 0, 15, 1, v => v + '%',
            () => applySettingsToRendition(epubRendition));
    }

    // =================== EPUB ===================
    function initEpub() {
        const book      = ePub(cfg.fileUrl);
        const container = document.getElementById('epub-container');
        const prevBtn   = document.getElementById('epub-prev');
        const nextBtn   = document.getElementById('epub-next');

        const rendition = book.renderTo(container, {
            width:  '100%',
            height: '100%',
            spread: 'none',
        });
        epubRendition = rendition;

        // Apply saved/default settings as initial theme
        applySettingsToRendition(rendition);

        const display = cfg.savedCfi ? rendition.display(cfg.savedCfi) : rendition.display();
        display.then(hideLoading);

        // Generate locations for percentage seek
        book.ready.then(() => {
            book.locations.generate(1024).then(() => {
                if (cfg.savedCfi) {
                    const pct = book.locations.percentageFromCfi(cfg.savedCfi) * 100;
                    setProgressUI(pct);
                    if (pageInfo) pageInfo.textContent = pct.toFixed(0) + '% read';
                }
            });

            // Build TOC list
            book.loaded.navigation.then(nav => {
                const tocList = document.getElementById('toc-list');
                if (!tocList) return;
                const items = (nav.toc || []);
                if (!items.length) {
                    tocList.innerHTML = '<p style="color:var(--text2);font-size:13px">No chapters found.</p>';
                    return;
                }
                tocList.innerHTML = items.map(item =>
                    '<button class="toc-item" data-href="' + escAttr(item.href) + '">'
                    + escHtml(item.label.trim()) + '</button>'
                ).join('');
                tocList.querySelectorAll('.toc-item').forEach(btn => {
                    btn.addEventListener('click', () => {
                        rendition.display(btn.dataset.href);
                        closeTocPanel();
                    });
                });
            });
        });

        // On chapter change
        rendition.on('relocated', location => {
            const pct = (location.start.percentage || 0) * 100;
            setProgressUI(pct);
            if (pageInfo) pageInfo.textContent = pct.toFixed(0) + '% read';

            // Update chapter subtitle
            if (chapterEl) {
                book.loaded.navigation.then(nav => {
                    const href = location.start.href || '';
                    const chapter = (nav.toc || []).find(item =>
                        item.href && href.includes(item.href.split('#')[0])
                    );
                    chapterEl.textContent = chapter ? chapter.label.trim() : '';
                });
            }

            if (book.locations && book.locations.total) {
                scheduleProgressSave({
                    cfi_position: location.start.cfi,
                    page_number:  0,
                    percentage:   parseFloat(pct.toFixed(2)),
                });
            }
        });

        // Animated page navigation
        function epubNav(direction) {
            const cls = direction === 'next' ? 'page-turning' : 'page-turning-back';
            container.classList.add(cls);
            setTimeout(() => { direction === 'next' ? rendition.next() : rendition.prev(); }, 0);
            setTimeout(() => container.classList.remove(cls), 250);
        }

        // Keyboard navigation
        document.addEventListener('keydown', e => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') epubNav('next');
            if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   epubNav('prev');
        });
        if (prevBtn) prevBtn.addEventListener('click', () => epubNav('prev'));
        if (nextBtn) nextBtn.addEventListener('click', () => epubNav('next'));

        // Mobile bottom-bar buttons
        const mobilePrev = document.getElementById('mobile-prev');
        const mobileNext = document.getElementById('mobile-next');
        if (mobilePrev) mobilePrev.addEventListener('click', () => {
            console.log('prev tapped');
            rendition.prev();
        });
        if (mobileNext) mobileNext.addEventListener('click', () => {
            console.log('next tapped');
            rendition.next();
        });

        // Swipe overlay — primary touch handler on mobile (sits above the iframe)
        const swipeOverlay = document.getElementById('swipe-overlay');
        if (swipeOverlay) {
            let startX = 0, startY = 0, startTime = 0;
            swipeOverlay.addEventListener('touchstart', e => {
                startX    = e.touches[0].clientX;
                startY    = e.touches[0].clientY;
                startTime = Date.now();
            }, { passive: true });
            swipeOverlay.addEventListener('touchmove', e => {
                const dx = Math.abs(e.touches[0].clientX - startX);
                const dy = Math.abs(e.touches[0].clientY - startY);
                if (dx > dy && dx > 10) e.preventDefault();
            }, { passive: false });
            swipeOverlay.addEventListener('touchend', e => {
                const dx      = e.changedTouches[0].clientX - startX;
                const dy      = e.changedTouches[0].clientY - startY;
                const elapsed = Date.now() - startTime;
                const absDx   = Math.abs(dx);
                const absDy   = Math.abs(dy);
                if (absDx < 10 && absDy < 10) {
                    document.body.classList.toggle('ui-hidden');
                    return;
                }
                if (absDx > 30 && absDx > absDy && elapsed < 500) {
                    dx < 0 ? rendition.next() : rendition.prev();
                }
            }, { passive: true });
        }

        // Touch on outer epub container (fires when iframe hasn't captured the touch)
        let tcx = 0, tcy = 0, tct = 0;
        container.addEventListener('touchstart', e => {
            tcx = e.changedTouches[0].clientX;
            tcy = e.changedTouches[0].clientY;
            tct = Date.now();
        }, { passive: true });
        container.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - tcx;
            const dy = Math.abs(e.changedTouches[0].clientY - tcy);
            const dt = Date.now() - tct;
            if (Math.abs(dx) > 30 && dy < Math.abs(dx) && dt < 400) {
                epubNav(dx < 0 ? 'next' : 'prev');
            } else if (Math.abs(dx) < 10 && dy < 10) {
                document.body.classList.toggle('ui-hidden');
            }
        }, { passive: true });

        // Inject touch + CSS into epub iframe on every render (iOS fix)
        rendition.on('rendered', () => {
            const iframeDoc = container.querySelector('iframe')?.contentDocument;
            if (!iframeDoc || iframeDoc._thgListeners) return;
            iframeDoc._thgListeners = true;

            // Disable text selection so swipe doesn't trigger text-select mode
            const sel = iframeDoc.createElement('style');
            sel.textContent = '* { -webkit-user-select: none !important; user-select: none !important; -webkit-touch-callout: none !important; }';
            (iframeDoc.head || iframeDoc.documentElement).appendChild(sel);

            let sx = 0, sy = 0, st = 0;
            iframeDoc.addEventListener('touchstart', e => {
                sx = e.changedTouches[0].clientX;
                sy = e.changedTouches[0].clientY;
                st = Date.now();
            }, { passive: true });
            // Always block default — paginated view has no meaningful scroll
            iframeDoc.addEventListener('touchmove', e => {
                e.preventDefault();
            }, { passive: false });
            iframeDoc.addEventListener('touchend', e => {
                const dx = e.changedTouches[0].clientX - sx;
                const dy = Math.abs(e.changedTouches[0].clientY - sy);
                const dt = Date.now() - st;
                if (Math.abs(dx) > 30 && dy < Math.abs(dx) && dt < 400) {
                    epubNav(dx < 0 ? 'next' : 'prev');
                } else if (Math.abs(dx) < 10 && dy < 10 && dt < 300) {
                    document.body.classList.toggle('ui-hidden');
                }
            }, { passive: true });
            iframeDoc.addEventListener('keydown', e => {
                if (e.key === 'ArrowRight') epubNav('next');
                if (e.key === 'ArrowLeft')  epubNav('prev');
                if (e.key === 'Escape') { closeSettingsPanel(); closeTocPanel(); }
            });
        });

        // Progress bar click-to-seek
        document.getElementById('reader-progress-bar')?.addEventListener('click', e => {
            if (!book.locations || !book.locations.total) return;
            const bar  = e.currentTarget;
            const pct  = Math.max(0, Math.min(1, (e.clientX - bar.getBoundingClientRect().left) / bar.offsetWidth));
            const cfi  = book.locations.cfiFromPercentage(pct);
            if (cfi) rendition.display(cfi);
        });

        // TOC panel
        const openToc  = () => openPanel('toc-panel', 'toc-backdrop');
        const closeToc = () => closePanel('toc-panel', 'toc-backdrop');
        closeTocPanel = closeToc;
        document.getElementById('toc-btn')?.addEventListener('click', openToc);
        document.getElementById('toc-backdrop')?.addEventListener('click', closeToc);
        document.getElementById('toc-close')?.addEventListener('click', closeToc);
    }

    // =================== PDF ===================
    function initPdf() {
        if (typeof pdfjsLib === 'undefined') { console.error('PDF.js not loaded'); return; }
        pdfjsLib.GlobalWorkerOptions.workerSrc =
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const container = document.getElementById('pdf-container');
        const prevBtn   = document.getElementById('pdf-prev');
        const nextBtn   = document.getElementById('pdf-next');

        let pdfDoc      = null;
        let currentPage = cfg.savedPage > 0 ? cfg.savedPage : 1;
        let totalPages  = 0;
        let rendering   = false;

        pdfjsLib.getDocument(cfg.fileUrl).promise.then(pdf => {
            pdfDoc     = pdf;
            totalPages = pdf.numPages;
            renderPage(currentPage);
        }).catch(err => {
            hideLoading();
            container.innerHTML = '<p style="color:var(--text2);text-align:center;padding:32px">Failed to load PDF: ' + err.message + '</p>';
        });

        function renderPage(num) {
            if (rendering) return;
            rendering = true;
            pdfDoc.getPage(num).then(page => {
                const scale    = devicePixelRatio > 1 ? 1.8 : 1.4;
                const viewport = page.getViewport({ scale });
                const canvas   = document.createElement('canvas');
                canvas.className   = 'pdf-page-canvas';
                canvas.width       = viewport.width;
                canvas.height      = viewport.height;
                canvas.style.width  = (viewport.width  / scale) + 'px';
                canvas.style.height = (viewport.height / scale) + 'px';
                container.innerHTML = '';
                container.appendChild(canvas);
                page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise.then(() => {
                    rendering   = false;
                    currentPage = num;
                    const pct   = (num / totalPages) * 100;
                    setProgressUI(pct);
                    if (pageInfo) pageInfo.textContent = 'Page ' + num + ' of ' + totalPages;
                    scheduleProgressSave({ cfi_position: null, page_number: num, percentage: parseFloat(pct.toFixed(2)) });
                    hideLoading();
                });
            });
        }

        function goNext() { if (currentPage < totalPages && !rendering) renderPage(currentPage + 1); }
        function goPrev() { if (currentPage > 1 && !rendering) renderPage(currentPage - 1); }

        if (prevBtn) prevBtn.addEventListener('click', goPrev);
        if (nextBtn) nextBtn.addEventListener('click', goNext);

        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') goNext();
            if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   goPrev();
        });

        let tpx = 0, tpy = 0;
        container.addEventListener('touchstart', e => {
            tpx = e.changedTouches[0].clientX;
            tpy = e.changedTouches[0].clientY;
        }, { passive: true });
        container.addEventListener('touchend', e => {
            const dx = e.changedTouches[0].clientX - tpx;
            const dy = Math.abs(e.changedTouches[0].clientY - tpy);
            if (Math.abs(dx) > 40 && dy < 60) {
                dx < 0 ? goNext() : goPrev();
            } else if (Math.abs(dx) < 10 && dy < 10) {
                document.body.classList.toggle('ui-hidden');
            }
        }, { passive: true });

        // Progress bar click-to-seek
        document.getElementById('reader-progress-bar')?.addEventListener('click', e => {
            if (!totalPages) return;
            const bar  = e.currentTarget;
            const pct  = Math.max(0, Math.min(1, (e.clientX - bar.getBoundingClientRect().left) / bar.offsetWidth));
            const page = Math.max(1, Math.min(totalPages, Math.round(pct * totalPages) || 1));
            renderPage(page);
        });
    }

    // =================== Init ===================
    initSettingsUI();

    if (cfg.type === 'epub') {
        initEpub();
    } else if (cfg.type === 'pdf') {
        initPdf();
    }

    // Restore initial progress UI while content loads
    if (cfg.percentage > 0) {
        setProgressUI(cfg.percentage);
        if (pageInfo && cfg.type === 'epub') pageInfo.textContent = cfg.percentage.toFixed(0) + '% read';
    }

    // =================== Helpers ===================
    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(str) { return String(str).replace(/"/g, '&quot;'); }

}(window.cfg));
