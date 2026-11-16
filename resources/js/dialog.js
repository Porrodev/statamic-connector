(function () {
    if (window.porroDialog) return;
    window.porroDialog = true;

    // Buttons carrying data-porro-open open the <dialog> with that id. The
    // dialog holds its own form, so nothing is sent until it's confirmed
    // there. Without this script the button does nothing.
    document.addEventListener('click', function (event) {
        var button = event.target.closest && event.target.closest('[data-porro-open]');
        var dialog = button && document.getElementById(button.getAttribute('data-porro-open'));

        if (!dialog || typeof dialog.showModal !== 'function') return;

        event.preventDefault();
        dialog.showModal();
    });
})();
