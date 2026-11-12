(() => {
    'use strict';

    const baseUrl = (window.BLOODLINK_BASE_URL || '').replace(/\/$/, '');
    const banner = document.querySelector('[data-pwa-install-banner]');
    const updateBanner = document.querySelector('[data-pwa-update-banner]');
    const updateNow = updateBanner?.querySelector('[data-pwa-update-now]');
    const updateLater = updateBanner?.querySelector('[data-pwa-update-later]');
    const updateStatus = updateBanner?.querySelector('[data-pwa-update-status]');
    const updatePostponedKey = 'bloodlink-pwa-update-postponed';
    const startupSplash = document.querySelector('[data-app-splash]');
    const updateStrings = {
        busy: updateBanner?.dataset.busyMessage || '',
        failed: updateBanner?.dataset.failedMessage || ''
    };
    let updatePostponed = false;
    let updateRequested = false;
    let promptedWorker = null;
    let refreshing = false;
    let deferredPrompt = null;

    if (startupSplash && document.documentElement.dataset.appStartup === 'true') {
        const hideStartupSplash = () => {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                startupSplash.hidden = true;
                document.documentElement.removeAttribute('data-app-startup');
                return;
            }

            startupSplash.classList.add('is-exiting');
            let fallbackTimer;
            const finish = event => {
                if (event && (event.target !== startupSplash || event.propertyName !== 'opacity')) {
                    return;
                }
                window.clearTimeout(fallbackTimer);
                startupSplash.removeEventListener('transitionend', finish);
                startupSplash.hidden = true;
                document.documentElement.removeAttribute('data-app-startup');
            };
            startupSplash.addEventListener('transitionend', finish);
            fallbackTimer = window.setTimeout(() => finish(), 400);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', hideStartupSplash, { once: true });
        } else {
            hideStartupSplash();
        }
    }

    const storageGet = key => {
        try {
            return window.sessionStorage.getItem(key);
        } catch (error) {
            console.warn('BloodLink could not read a PWA session preference:', error);
            return null;
        }
    };
    const storageSet = (key, value) => {
        try {
            window.sessionStorage.setItem(key, value);
        } catch (error) {
            console.warn('BloodLink could not save a PWA session preference:', error);
        }
    };
    const storageRemove = key => {
        try {
            window.sessionStorage.removeItem(key);
        } catch (error) {
            console.warn('BloodLink could not clear a PWA session preference:', error);
        }
    };
    const hasCriticalOperation = () => Boolean(document.querySelector(
        'dialog[open], [data-loading], [aria-busy="true"], form[data-pwa-critical="true"]'
    ));

    if ('serviceWorker' in navigator && baseUrl) {
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (updateRequested && !refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });

        const setupUpdates = registration => {
            const showUpdate = worker => {
                if (!updateBanner || !worker || updatePostponed || worker === promptedWorker) {
                    return;
                }
                promptedWorker = worker;
                updateStatus.textContent = '';
                updateBanner.hidden = false;
            };
            const watchInstallingWorker = () => {
                const installing = registration.installing;
                if (!installing) {
                    return;
                }
                installing.addEventListener('statechange', () => {
                    if (installing.state === 'installed' && navigator.serviceWorker.controller) {
                        showUpdate(registration.waiting);
                    }
                });
            };

            updatePostponed = storageGet(updatePostponedKey) === '1';
            registration.addEventListener('updatefound', watchInstallingWorker);
            watchInstallingWorker();
            showUpdate(registration.waiting);

            updateLater?.addEventListener('click', () => {
                updatePostponed = true;
                storageSet(updatePostponedKey, '1');
                updateBanner.hidden = true;
            });

            updateNow?.addEventListener('click', () => {
                if (hasCriticalOperation()) {
                    updateStatus.textContent = updateStrings.busy;
                    return;
                }

                const waitingWorker = registration.waiting;
                if (!waitingWorker) {
                    updateBanner.hidden = true;
                    return;
                }

                updateRequested = true;
                updateNow.disabled = true;
                updateStatus.textContent = '';
                waitingWorker.addEventListener('statechange', () => {
                    if (waitingWorker.state === 'redundant' && updateRequested) {
                        updateRequested = false;
                        updateNow.disabled = false;
                        updateStatus.textContent = updateStrings.failed;
                    }
                });
                try {
                    waitingWorker.postMessage({ type: 'SKIP_WAITING' });
                } catch (error) {
                    updateRequested = false;
                    updateNow.disabled = false;
                    updateStatus.textContent = updateStrings.failed;
                    console.error('BloodLink could not activate the waiting app update:', error);
                }
            });
        };

        window.addEventListener('load', () => {
            navigator.serviceWorker.register(`${baseUrl}/sw.js`).then(setupUpdates).catch(error => {
                console.error('BloodLink service worker registration failed:', error);
            });
        }, { once: true });
    }

    if (!banner) {
        return;
    }

    const action = banner.querySelector('[data-pwa-install-action]');
    const description = banner.querySelector('[data-pwa-install-description]');
    const dismiss = banner.querySelector('[data-pwa-install-dismiss]');
    const dismissedKey = 'bloodlink-pwa-install-dismissed';
    const strings = {
        description: description.textContent,
        install: action.textContent,
        iosDescription: banner.dataset.iosDescription,
        iosAction: banner.dataset.iosAction,
        iosDone: banner.dataset.iosDone,
        manualDescription: banner.dataset.manualDescription,
        manualAction: banner.dataset.manualAction,
        installFailed: banner.dataset.installFailed
    };
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    let showingIOSHelp = false;
    let showingManualHelp = false;

    const showBanner = (explicit = false) => {
        if (!isStandalone) {
            if (!explicit && storageGet(dismissedKey) === '1') {
                return;
            }
            if (explicit) {
                storageRemove(dismissedKey);
            }
            description.textContent = strings.description;
            action.textContent = isIOS && !deferredPrompt ? strings.iosAction : strings.install;
            showingIOSHelp = false;
            showingManualHelp = false;
            banner.hidden = false;
        }
    };

    document.querySelectorAll('[data-pwa-install-open]').forEach(link => {
        link.addEventListener('click', event => {
            event.preventDefault();
            showBanner(true);
            if (!banner.hidden) {
                action.focus();
            }
        });
    });

    window.addEventListener('beforeinstallprompt', event => {
        event.preventDefault();
        deferredPrompt = event;
        showBanner();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        banner.hidden = true;
        document.querySelectorAll('[data-pwa-install-open]').forEach(link => {
            link.hidden = true;
        });
    });

    if (isIOS && !isStandalone) {
        action.textContent = strings.iosAction;
        showBanner();
    }

    action.addEventListener('click', async () => {
        if (isIOS && !deferredPrompt) {
            if (showingIOSHelp) {
                banner.hidden = true;
                return;
            }

            showingIOSHelp = true;
            description.textContent = strings.iosDescription;
            action.textContent = strings.iosDone;
            return;
        }

        if (!deferredPrompt) {
            if (showingManualHelp) {
                banner.hidden = true;
                return;
            }

            showingManualHelp = true;
            description.textContent = strings.manualDescription;
            action.textContent = strings.manualAction;
            return;
        }

        const installPrompt = deferredPrompt;
        deferredPrompt = null;
        try {
            await installPrompt.prompt();
            await installPrompt.userChoice;
            banner.hidden = true;
        } catch (error) {
            console.error('BloodLink app installation prompt failed:', error);
            banner.hidden = true;
            description.textContent = strings.installFailed;
            action.textContent = strings.manualAction;
            showingManualHelp = true;
            banner.hidden = false;
        }
    });

    dismiss.addEventListener('click', () => {
        banner.hidden = true;
        storageSet(dismissedKey, '1');
    });

    if (isStandalone) {
        document.querySelectorAll('[data-pwa-install-open]').forEach(link => {
            link.hidden = true;
        });
    }
})();
