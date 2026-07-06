<?php

namespace Drupal\bongolava_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Formulaire pour activer / désactiver un compte recruteur (protégé CSRF).
 */
final class RecruiterToggleStatusForm extends FormBase {

  public function getFormId(): string {
    return 'bongolava_admin_recruiter_toggle_status';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $user_id = NULL): array {
    if (empty($user_id)) {
      return ['#markup' => $this->t('Utilisateur inconnu.')];
    }

    $user = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);
    if (!$user) {
      return ['#markup' => $this->t('Utilisateur inconnu.')];
    }

    $is_active = $user->isActive();
    // Do not force an action; let Drupal handle the form POST processing.

    $form['user_id'] = ['#type' => 'value', '#value' => $user->id()];
    $form['action'] = ['#type' => 'value', '#value' => $is_active ? 'block' : 'activate'];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $is_active ? $this->t('Désactiver le compte') : $this->t('Activer le compte'),
      '#button_type' => 'primary',
      '#attributes' => ['class' => ['button']],
    ];

    // Log that the form was built for debugging.
    \Drupal::logger('bongolava_admin')->notice('Recruiter toggle form built for uid: @uid', ['@uid' => $user->id()]);

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    \Drupal::logger('bongolava_admin')->notice('Recruiter toggle submit handler entered.');

    $uid = $form_state->getValue('user_id');
    $action = $form_state->getValue('action');

    $user = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    if (!$user instanceof UserInterface) {
      $this->messenger()->addError($this->t('Utilisateur introuvable.'));
      return;
    }

    \Drupal::logger('bongolava_admin')->notice('Recruiter toggle submit for uid: @uid action: @action', ['@uid' => $uid, '@action' => $action]);

    if ($action === 'block') {
      $user->block();
      $this->messenger()->addStatus($this->t('Le compte @name a été désactivé.', ['@name' => $user->getDisplayName()]));
    }
    else {
      $user->activate();
      $this->messenger()->addStatus($this->t('Le compte @name a été activé.', ['@name' => $user->getDisplayName()]));
    }
    $user->save();

    // Redirect to the recruiters list after the action.
    $form_state->setRedirect('bongolava_admin.recruiters_list');
  }

}
