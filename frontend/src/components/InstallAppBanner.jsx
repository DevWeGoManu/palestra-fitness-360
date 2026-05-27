import { useEffect, useMemo, useState } from 'react';

const DISMISS_KEY = 'athleodesk-install-banner-dismissed';

export function InstallAppBanner() {
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [isMobile, setIsMobile] = useState(false);
  const [isStandalone, setIsStandalone] = useState(false);
  const [sessionHidden, setSessionHidden] = useState(false);
  const [dismissed, setDismissed] = useState(() => {
    try {
      return window.localStorage.getItem(DISMISS_KEY) === '1';
    } catch {
      return false;
    }
  });
  const [status, setStatus] = useState('');

  const platform = useMemo(() => getPlatform(), []);

  useEffect(() => {
    const mobileQuery = window.matchMedia('(max-width: 760px)');
    const standaloneQuery = window.matchMedia('(display-mode: standalone)');

    const updateState = () => {
      setIsMobile(mobileQuery.matches);
      setIsStandalone(standaloneQuery.matches || Boolean(window.navigator.standalone));
    };

    updateState();
    mobileQuery.addEventListener('change', updateState);
    standaloneQuery.addEventListener('change', updateState);
    return () => {
      mobileQuery.removeEventListener('change', updateState);
      standaloneQuery.removeEventListener('change', updateState);
    };
  }, []);

  useEffect(() => {
    const handleBeforeInstallPrompt = (event) => {
      event.preventDefault();
      setDeferredPrompt(event);
    };
    const handleAppInstalled = () => {
      setDeferredPrompt(null);
      setDismissed(true);
      try {
        window.localStorage.setItem(DISMISS_KEY, '1');
      } catch {}
    };

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);
    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
      window.removeEventListener('appinstalled', handleAppInstalled);
    };
  }, []);

  if (!isMobile || isStandalone || dismissed || sessionHidden) {
    return null;
  }

  const canPrompt = Boolean(deferredPrompt);
  const message = getMessage(platform, canPrompt);

  async function install() {
    if (!deferredPrompt) {
      setStatus(message);
      return;
    }

    deferredPrompt.prompt();
    const choice = await deferredPrompt.userChoice;
    setDeferredPrompt(null);

    if (choice.outcome === 'accepted') {
      setStatus('Installazione avviata.');
      setSessionHidden(true);
      return;
    }

    setStatus('Installazione non avviata.');
  }

  function hideForSession() {
    setSessionHidden(true);
  }

  function hidePermanently() {
    setDismissed(true);
    try {
      window.localStorage.setItem(DISMISS_KEY, '1');
    } catch {}
  }

  return (
    <aside className="install-banner" aria-label="Installa AthleoDesk sul telefono">
      <div className="install-banner-copy">
        <strong>Installa AthleoDesk</strong>
        <span aria-live="polite">{status || message}</span>
      </div>
      <div className="install-banner-actions">
        <button className="primary install-banner-primary" type="button" onClick={install}>Installa app</button>
        <button className="ghost install-banner-secondary" type="button" onClick={hideForSession}>Non ora</button>
        <button className="install-banner-dismiss" type="button" onClick={hidePermanently}>Non mostrare più</button>
      </div>
    </aside>
  );
}

function getPlatform() {
  const ua = window.navigator.userAgent || '';
  const vendor = window.navigator.vendor || '';
  const isIos = /iPad|iPhone|iPod/.test(ua) || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
  const isDuckDuckGo = /DuckDuckGo|DuckDuckGo-Favicons-Browser/.test(ua);
  const isAndroid = /Android/i.test(ua);
  const isChrome = /Chrome|CriOS/i.test(ua) && /Google Inc/.test(vendor);

  if (isIos) return 'ios';
  if (isDuckDuckGo) return 'unsupported';
  if (isAndroid && isChrome) return 'android-chrome';
  return 'unsupported';
}

function getMessage(platform, canPrompt) {
  if (canPrompt) {
    return "Aggiungi l'app alla schermata Home per aprirla piu velocemente.";
  }
  if (platform === 'ios') {
    return 'Apri Safari, tocca Condividi e poi Aggiungi alla schermata Home.';
  }
  return "Se non compare l'installazione, apri il sito con Chrome su Android o Safari su iPhone.";
}
