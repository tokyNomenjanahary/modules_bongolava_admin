<?php

namespace Drupal\bongolava_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filtres GET pour la liste des recruteurs.
 */
final class RecruiterListFiltersForm extends FormBase {

  public function getFormId(): string {
    return 'bongolava_admin_recruiter_list_filters';
  }

  public function buildForm(array $form, FormStateInterface $form_state, array $filters = []): array {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('bongolava_admin.recruiters_list')->toString();
    $form['#token'] = FALSE;

    // Render inline: add classes used by the module CSS to align items horizontally.
    $form['#attributes']['class'][] = 'form--inline';
    $form['#attributes']['class'][] = 'bongolava-admin-filters-form';

    $form['keyword'] = [
      '#type' => 'search',
      '#title' => $this->t('Rechercher par email'),
      '#placeholder' => $this->t('Adresse email'),
      '#default_value' => $filters['keyword'] ?? '',
    ];
    $form['keyword']['#wrapper_attributes']['class'][] = 'bongolava-admin-filters__item';

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Statut du compte'),
      '#options' => [
        '' => $this->t('Tous'),
        'active' => $this->t('Actif'),
        'blocked' => $this->t('Bloqué'),
      ],
      '#default_value' => $filters['status'] ?? '',
    ];
    $form['status']['#wrapper_attributes']['class'][] = 'bongolava-admin-filters__item';

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filtrer'),
      '#button_type' => 'primary',
    ];
    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Réinitialiser'),
      '#url' => Url::fromRoute('bongolava_admin.recruiters_list'),
      '#attributes' => ['class' => ['button']],
    ];
    $form['actions']['#attributes']['class'][] = 'bongolava-admin-filters-actions';

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET form — no server-side submit handling.
  }

}
