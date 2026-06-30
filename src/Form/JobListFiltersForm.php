<?php

namespace Drupal\bongolava_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\bongolava_admin\Service\JobListQueryService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Filtres GET pour la liste des offres d'emploi.
 */
final class JobListFiltersForm extends FormBase {

  public function __construct(
    private readonly JobListQueryService $listQuery,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('bongolava_admin.job_list_query'));
  }

  public function getFormId(): string {
    return 'bongolava_admin_job_list_filters';
  }

  public function buildForm(array $form, FormStateInterface $form_state, array $filters = []): array {
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('bongolava_admin.jobs_list')->toString();
    $form['#token'] = FALSE;
    $form['#attributes']['class'][] = 'bongolava-admin-filters-form';

    $form['keyword'] = [
      '#type' => 'search',
      '#title' => $this->t('Mot-clé'),
      '#placeholder' => $this->t('Titre, entreprise, description…'),
      '#default_value' => $filters['keyword'] ?? '',
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Statut'),
      '#options' => $this->listQuery->statusOptions(),
      '#default_value' => $filters['status'] ?? '',
    ];

    $form['user_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Publié par'),
      '#options' => $this->listQuery->userTypeOptions(),
      '#default_value' => $filters['user_type'] ?? '',
    ];

    $form['sector'] = [
      '#type' => 'select',
      '#title' => $this->t('Secteur'),
      '#options' => $this->listQuery->taxonomyOptions('secteur'),
      '#default_value' => $filters['sector'] ?? '',
    ];

    $form['contract_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type de contrat'),
      '#options' => $this->listQuery->taxonomyOptions('type_contrat'),
      '#default_value' => $filters['contract_type'] ?? '',
    ];

    $form['location'] = [
      '#type' => 'select',
      '#title' => $this->t('Localisation'),
      '#options' => $this->listQuery->taxonomyOptions('localisation'),
      '#default_value' => $filters['location'] ?? '',
    ];

    $form['actions'] = ['#type' => 'actions', '#attributes' => ['class' => ['bongolava-admin-filters-actions']]];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filtrer'),
      '#button_type' => 'primary',
    ];
    $form['actions']['reset'] = [
      '#type' => 'link',
      '#title' => $this->t('Réinitialiser'),
      '#url' => Url::fromRoute('bongolava_admin.jobs_list'),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // GET form — handled by query string.
  }

}
