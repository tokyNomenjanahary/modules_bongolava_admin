<?php

namespace Drupal\bongolava_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulaire de validation d'un événement (pending → published).
 */
final class EventApproveForm extends FormBase {

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
    return 'bongolava_admin_event_approve_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $this->node = $node;
    if (!$this->node) {
      return $form;
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Valider l\'événement'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->node) {
      return;
    }

    $status = (string) $this->node->get('field_status')->value;
    if ($status !== 'pending') {
      $this->messenger()->addError($this->t('Seuls les événements en attente peuvent être validés.'));
      return;
    }

    $this->node->set('field_status', 'published');
    $this->node->save();

    $this->messenger()->addStatus($this->t('L\'événement « @title » a été publié.', [
      '@title' => $this->node->label(),
    ]));

    $form_state->setRedirect('bongolava_admin.event_review', ['node' => $this->node->id()]);
  }

}
