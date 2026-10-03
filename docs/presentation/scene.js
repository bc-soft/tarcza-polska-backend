// Wspólne sterowanie scenami filmu (intro, scene1..3).
// Samodzielnie: start po załadowaniu fontów i obrazków; klik / spacja / R powtarza; ?delay=2 opóźnia start (s).
// W film.html (?autoplay=0): scena zgłasza gotowość, startuje na wiadomość od rodzica i zgłasza koniec animacji.
(() => {
    const params = new URLSearchParams(location.search);
    const delay = parseFloat(params.get('delay'));
    if (!Number.isNaN(delay)) document.documentElement.style.setProperty('--delay', delay + 's');
    const embedded = params.get('autoplay') === '0';
    const notify = type => parent !== window && parent.postMessage({type}, '*');
    let run = 0;

    function play() {
        const id = ++run;
        document.body.classList.remove('play');
        void document.body.offsetWidth; // restart animacji
        document.body.classList.add('play');
        requestAnimationFrame(() => Promise.all(document.getAnimations().map(a => a.finished))
            .then(() => id === run && notify('tarcza:scene-done'), () => {}));
    }

    const ready = Promise.all([
        document.fonts.ready,
        ...[...document.images].map(img => img.complete ? null : new Promise(r => { img.onload = img.onerror = r; })),
    ]);
    ready.then(() => embedded ? notify('tarcza:scene-ready') : play());
    addEventListener('message', e => { if (e.data?.type === 'tarcza:play') ready.then(play); });
    if (!embedded) {
        addEventListener('click', play);
        addEventListener('keydown', e => { if (e.key === ' ' || e.key.toLowerCase() === 'r') { e.preventDefault(); play(); } });
    }
})();
