/**
 * Wolf Tactical Security Script
 * Disables common inspection tools and right-click to deter casual tampering.
 */

(function() {
    'use strict';

    // Disable Right Click
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });

    // Disable Keyboard Shortcuts for Inspector
    document.addEventListener('keydown', function(e) {
        // F12
        if (e.key === 'F12' || e.keyCode === 123) {
            e.preventDefault();
            return false;
        }

        // Ctrl+Shift+I (Inspector)
        if (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'i' || e.keyCode === 73)) {
            e.preventDefault();
            return false;
        }

        // Ctrl+Shift+J (Console)
        if (e.ctrlKey && e.shiftKey && (e.key === 'J' || e.key === 'j' || e.keyCode === 74)) {
            e.preventDefault();
            return false;
        }

        // Ctrl+Shift+C (Element Inspector)
        if (e.ctrlKey && e.shiftKey && (e.key === 'C' || e.key === 'c' || e.keyCode === 67)) {
            e.preventDefault();
            return false;
        }

        // Ctrl+U (View Source)
        if (e.ctrlKey && (e.key === 'U' || e.key === 'u' || e.keyCode === 85)) {
            e.preventDefault();
            return false;
        }
    });

    // Console Warning
    if (window.console) {
        console.log('%c¡DETENTE!', 'color: red; font-size: 50px; font-weight: bold; text-shadow: 2px 2px black;');
        console.log('%cEsta es una función del navegador para desarrolladores. Si alguien te dijo que copiaras y pegaras algo aquí para habilitar una función o "hackear" la cuenta de alguien, es una estafa y te dará acceso a tu cuenta.', 'font-size: 20px;');
    }
})();
