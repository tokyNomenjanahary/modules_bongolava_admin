(function (Drupal, once) {
  'use strict';

  function openDialog(dialog) {
    dialog.hidden = false;
    document.body.style.overflow = 'hidden';
  }

  function closeDialog(dialog) {
    dialog.hidden = true;
    document.body.style.overflow = '';
  }

  Drupal.behaviors.bongolavaAdminModeration = {
    attach(context) {
      once('bongolava-admin-open-cancel', '.bongolava-admin-open-cancel', context).forEach((button) => {
        button.addEventListener('click', () => {
          const dialogId = button.getAttribute('data-dialog');
          const dialog = dialogId ? document.getElementById(dialogId) : null;
          if (dialog) {
            openDialog(dialog);
          }
        });
      });

      once('bongolava-admin-close-dialog', '[data-close-dialog]', context).forEach((el) => {
        el.addEventListener('click', () => {
          const dialog = el.closest('.bongolava-admin-dialog');
          if (dialog) {
            closeDialog(dialog);
          }
        });
      });

      once('bongolava-admin-dialog-escape', '.bongolava-admin-dialog', context).forEach((dialog) => {
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !dialog.hidden) {
            closeDialog(dialog);
          }
        });
      });
    },
  };
})(Drupal, once);
