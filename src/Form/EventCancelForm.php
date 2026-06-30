<?php

namespace Drupal\bongolava_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulaire d'annulation d'un événement (pending → cancelled).
 */
final class EventCancelForm extends FormBase {

  private ?NodeInterface $node = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bongolava_admin_event_cancel_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $this->node = $node;
    if (!$this->node) {
      return $form;
    }

    $status = (string) $this->node->get('field_status')->value;
    if ($status !== 'pending') {
      $this->messenger()->addWarning($this->t('Cet événement n\'est plus en attente de validation.'));
    }

    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Événement'),
      '#markup' => '<strong>' . $this->node->label() . '</strong>',
    ];

    $form['reason'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Raison de l\'annulation'),
      '#description' => $this->t('Ce message sera enregistré dans le champ raison d\'annulation.'),
      '#required' => TRUE,
      '#rows' => 5,
      '#default_value' => (string) ($this->node->get('field_raison_annulation')->value ?? ''),
      '#attributes' => ['class' => ['bongolava-admin-cancel-reason']],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirmer l\'annulation'),
      '#button_type' => 'danger',
      '#disabled' => $status !== 'pending',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Retour'),
      '#url' => Url::fromRoute('bongolava_admin.event_review', ['node' => $this->node->id()]),
      '#attributes' => ['class' => ['button']],
    ];

    $form['#attached']['library'][] = 'bongolava_admin/moderation';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    if (!$this->node) {
      $form_state->setErrorByName('reason', $this->t('Événement introuvable.'));
      return;
    }

    if ((string) $this->node->get('field_status')->value !== 'pending') {
      $form_state->setErrorByName('reason', $this->t('Seuls les événements en attente peuvent être annulés.'));
    }

    if (trim((string) $form_state->getValue('reason')) === '') {
      $form_state->setErrorByName('reason', $this->t('La raison de l\'annulation est obligatoire.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->node) {
      return;
    }

    $this->node->set('field_raison_annulation', trim((string) $form_state->getValue('reason')));
    $this->node->set('field_status', 'cancelled');
    $this->node->save();

    $this->messenger()->addStatus($this->t('L\'événement « @title » a été annulé.', [
      '@title' => $this->node->label(),
    ]));

    $form_state->setRedirect('bongolava_admin.event_review', ['node' => $this->node->id()]);
  }

}
