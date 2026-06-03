import { router } from '@inertiajs/vue3';

const actionLoadingDuration = 450;
const navigationFallbackDuration = 1500;
const navigationElements = new Set<HTMLElement>();

function getLoadingLabel(element: HTMLElement): string {
    if (element.dataset.loadingMode === 'spinner-only') {
        return '';
    }

    return element.innerText
        .trim()
        .replace(/^[^\p{L}\p{N}]+/u, '')
        .trim();
}

function getVisibleBackground(element: HTMLElement): string {
    let current: HTMLElement | null = element;

    while (current) {
        const backgroundColor = window.getComputedStyle(current).backgroundColor;

        if (backgroundColor !== 'rgba(0, 0, 0, 0)' && backgroundColor !== 'transparent') {
            return backgroundColor;
        }

        current = current.parentElement;
    }

    return window.getComputedStyle(document.body).backgroundColor;
}

function isNavigationElement(element: HTMLElement): boolean {
    if (element.dataset.clickFeedback === 'action') {
        return false;
    }

    if (element.dataset.navigationButton === 'true') {
        return true;
    }

    if (!(element instanceof HTMLAnchorElement)) {
        return false;
    }

    const href = element.getAttribute('href');

    return Boolean(
        href &&
        href !== '#' &&
        !href.startsWith('javascript:') &&
        !element.hasAttribute('download'),
    );
}

function clearNavigationState(): void {
    navigationElements.forEach((element) => {
        element.classList.remove('is-navigation-disabled');
        element.removeAttribute('aria-disabled');
    });
    navigationElements.clear();
}

export function initializeClickFeedback(): void {
    if (document.documentElement.dataset.clickFeedbackInitialized === 'true') {
        return;
    }

    document.documentElement.dataset.clickFeedbackInitialized = 'true';

    document.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const element = target.closest<HTMLElement>('button, a[href], [role="button"]');

        if (
            !element ||
            element.dataset.clickFeedback === 'none' ||
            element.matches(':disabled') ||
            element.getAttribute('aria-disabled') === 'true'
        ) {
            return;
        }

        if (isNavigationElement(element)) {
            element.classList.add('is-navigation-disabled');
            element.setAttribute('aria-disabled', 'true');
            navigationElements.add(element);

            window.setTimeout(() => {
                if (document.contains(element)) {
                    element.classList.remove('is-navigation-disabled');
                    element.removeAttribute('aria-disabled');
                }

                navigationElements.delete(element);
            }, navigationFallbackDuration);

            return;
        }

        const loadingLabel = getLoadingLabel(element);
        const styles = window.getComputedStyle(element);

        element.dataset.loadingLabel = loadingLabel;
        element.style.setProperty('--click-loading-bg', getVisibleBackground(element));
        element.style.setProperty('--click-loading-color', styles.color);
        element.classList.remove('is-click-loading');
        element.classList.toggle('is-icon-loading', loadingLabel === '');
        void element.offsetWidth;
        element.classList.add('is-click-loading');

        window.setTimeout(() => {
            element.classList.remove('is-click-loading');
            element.classList.remove('is-icon-loading');
            delete element.dataset.loadingLabel;
            element.style.removeProperty('--click-loading-bg');
            element.style.removeProperty('--click-loading-color');
        }, actionLoadingDuration);
    });

    router.on('finish', clearNavigationState);
}
