<?php

namespace Drupal\bongolava_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formulaire de validation d'une offre d'emploi (pending → published).
 */
final class JobApproveForm extends FormBase {

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
    return 'bongolava_admin_job_approve_form';
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
      '#value' => $this->t('Valider l\'offre'),
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

    $status = (string) $this->node->get('field_status_offre')->value;
    if ($status !== 'pending') {
      $this->messenger()->addError($this->t('Seules les offres en attente peuvent être validées.'));
      return;
    }

    $this->node->set('field_status_offre', 'published');
    $this->node->save();

    $this->messenger()->addStatus($this->t('L\'offre « @title » a été publiée.', [
      '@title' => $this->node->label(),
    ]));

    $form_state->setRedirect('bongolava_admin.job_review', ['node' => $this->node->id()]);
  }

}
