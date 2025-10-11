(function () {
    'use strict';

    const bodyClass = 'woo-check-login-modal-open';

    const focusableSelectors = [
        'a[href]',
        'button:not([disabled])',
        'textarea:not([disabled])',
        'input:not([type="hidden"]):not([disabled])',
        'select:not([disabled])',
        '[tabindex]:not([tabindex="-1"])'
    ];

    const toggleScroll = (shouldLock) => {
        document.body.classList.toggle(bodyClass, shouldLock);
    };

    const trapFocus = (modal, event) => {
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = modal.querySelectorAll(focusableSelectors.join(','));
        if (!focusable.length) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const focusFirstField = (modal) => {
        const preferredSelectors = [
            'input[type="text"]',
            'input[type="email"]',
            'input[type="password"]',
            'input',
            'button',
            '[tabindex]'
        ];

        for (const selector of preferredSelectors) {
            const element = modal.querySelector(selector);
            if (element) {
                element.focus();
                return;
            }
        }
    };

    const getModalByTrigger = (trigger) => {
        const targetId = trigger.getAttribute('data-woo-check-modal-target');
        if (!targetId) {
            return null;
        }
        return document.getElementById(targetId) || null;
    };

    const closeModal = (modal, trigger) => {
        if (!modal) {
            return;
        }

        const originTrigger = trigger || modal.__wooCheckTrigger || null;

        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('data-woo-check-open');
        modal.removeEventListener('keydown', modal.__wooCheckFocusTrap);
        delete modal.__wooCheckFocusTrap;

        const activeDismissButton = modal.querySelector('[data-woo-check-modal-dismiss]');
        if (activeDismissButton) {
            activeDismissButton.blur();
        }

        toggleScroll(false);

        if (originTrigger) {
            originTrigger.focus();
        }

        modal.__wooCheckTrigger = null;
    };

    const openModal = (modal, trigger) => {
        if (!modal) {
            return;
        }

        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        modal.setAttribute('data-woo-check-open', 'true');

        const focusTrapHandler = (event) => trapFocus(modal, event);
        modal.__wooCheckFocusTrap = focusTrapHandler;
        modal.addEventListener('keydown', focusTrapHandler);

        focusFirstField(modal);
        toggleScroll(true);

        modal.__wooCheckTrigger = trigger || null;
    };

    const handleTriggerClick = (event) => {
        const trigger = event.currentTarget;
        const modal = getModalByTrigger(trigger);

        if (!modal) {
            return;
        }

        event.preventDefault();
        openModal(modal, trigger);
    };

    const handleModalClick = (event) => {
        const modal = event.currentTarget;
        const dismissTarget = event.target.closest('[data-woo-check-modal-dismiss]');

        if (dismissTarget) {
            event.preventDefault();
            closeModal(modal, modal.__wooCheckTrigger);
        }
    };

    const handleEscape = (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const openModalElement = document.querySelector('.woo-check-login-modal[data-woo-check-open="true"]');
        if (!openModalElement) {
            return;
        }

        event.preventDefault();
        closeModal(openModalElement, openModalElement.__wooCheckTrigger);
    };

    const init = () => {
        const triggers = document.querySelectorAll('[data-woo-check-modal-target]');
        if (!triggers.length) {
            return;
        }

        triggers.forEach((trigger) => {
            const modal = getModalByTrigger(trigger);
            if (!modal) {
                return;
            }

            trigger.addEventListener('click', handleTriggerClick);
            modal.addEventListener('click', handleModalClick);
        });

        document.addEventListener('keydown', handleEscape);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
