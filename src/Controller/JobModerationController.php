<?php

namespace Drupal\bongolava_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\bongolava_admin\Form\JobApproveForm;
use Drupal\bongolava_admin\Form\JobCancelForm;
use Drupal\bongolava_admin\Form\JobListFiltersForm;
use Drupal\bongolava_admin\Service\JobDisplayService;
use Drupal\bongolava_admin\Service\JobListQueryService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pages d'administration pour la modération des offres d'emploi.
 */
final class JobModerationController extends ControllerBase {

  public function __construct(
    private readonly JobDisplayService $display,
    private readonly JobListQueryService $listQuery,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('bongolava_admin.job_display'),
      $container->get('bongolava_admin.job_list_query'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Liste de toutes les offres avec filtres et pagination.
   */
  public function listAll(Request $request): array {
    $filters = [
      'keyword' => trim((string) $request->query->get('keyword', '')),
      'status' => trim((string) $request->query->get('status', '')),
      'user_type' => trim((string) $request->query->get('user_type', '')),
      'sector' => trim((string) $request->query->get('sector', '')),
      'contract_type' => trim((string) $request->query->get('contract_type', '')),
      'location' => trim((string) $request->query->get('location', '')),
    ];

    $page = max(0, (int) $request->query->get('page', 0));
    $result = $this->listQuery->search($filters, $page);

    $items = [];
    foreach ($result['items'] as $item) {
      $items[] = $item + [
        'detail_url' => Url::fromRoute('bongolava_admin.job_review', ['node' => $item['id']])->toString(),
      ];
    }

    return [
      '#theme' => 'bongolava_admin_content_list',
      '#list_type' => 'job',
      '#title' => (string) $this->t('Toutes les offres d\'emploi'),
      '#filter_form' => $this->formBuilder()->getForm(JobListFiltersForm::class, $filters),
      '#items' => $items,
      '#total' => $result['total'],
      '#empty_message' => (string) $this->t('Aucune offre trouvée.'),
      '#pager' => ['#type' => 'pager'],
      '#attached' => ['library' => ['bongolava_admin/moderation']],
    ];
  }

  /**
   * Liste des offres en attente de validation avec filtres et pagination.
   */
  public function listPending(Request $request): array {
    $filters = [
      'keyword' => trim((string) $request->query->get('keyword', '')),
      'status' => 'pending',
      'user_type' => trim((string) $request->query->get('user_type', '')),
      'sector' => trim((string) $request->query->get('sector', '')),
      'contract_type' => trim((string) $request->query->get('contract_type', '')),
      'location' => trim((string) $request->query->get('location', '')),
    ];

    $page = max(0, (int) $request->query->get('page', 0));
    $result = $this->listQuery->search($filters, $page);

    $items = [];
    foreach ($result['items'] as $item) {
      $items[] = $item + [
        'detail_url' => Url::fromRoute('bongolava_admin.job_review', ['node' => $item['id']])->toString(),
      ];
    }

    return [
      '#theme' => 'bongolava_admin_content_list',
      '#list_type' => 'job',
      '#title' => (string) $this->t('Offres en attente de validation'),
      '#filter_form' => $this->formBuilder()->getForm(JobListFiltersForm::class, $filters),
      '#items' => $items,
      '#total' => $result['total'],
      '#empty_message' => (string) $this->t('Aucune offre en attente trouvée.'),
      '#pager' => ['#type' => 'pager'],
      '#attached' => ['library' => ['bongolava_admin/moderation']],
    ];
  }

  /**
   * Page de détail d'une offre pour validation.
   */
  public function review(NodeInterface $node): array {
    $data = $this->display->build($node);
    $status = (string) ($data['status'] ?? '');
    $isPending = $status === 'pending';

    $approveForm = NULL;
    $cancelForm = NULL;
    if ($isPending) {
      $approveForm = $this->formBuilder()->getForm(JobApproveForm::class, $node);
      $cancelForm = $this->formBuilder()->getForm(JobCancelForm::class, $node);
    }

    return [
      '#theme' => 'bongolava_admin_job_detail',
      '#job' => $data,
      '#status_label' => $this->display->statusLabel($status),
      '#is_pending' => $isPending,
      '#approve_form' => $approveForm,
      '#cancel_form' => $cancelForm,
      '#cancel_url' => Url::fromRoute('bongolava_admin.job_cancel', ['node' => $node->id()])->toString(),
      '#back_url' => Url::fromRoute('bongolava_admin.jobs_list')->toString(),
      '#attached' => ['library' => ['bongolava_admin/moderation']],
    ];
  }

  public function reviewTitle(NodeInterface $node): string {
    return (string) $this->t('Validation offre : @title', ['@title' => $node->label()]);
  }

}
