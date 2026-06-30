<?php

namespace Drupal\bongolava_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\bongolava_admin\Form\EventApproveForm;
use Drupal\bongolava_admin\Form\EventCancelForm;
use Drupal\bongolava_admin\Form\EventListFiltersForm;
use Drupal\bongolava_admin\Service\EventDisplayService;
use Drupal\bongolava_admin\Service\EventListQueryService;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Pages d'administration pour la modération des événements.
 */
final class EventModerationController extends ControllerBase {

  public function __construct(
    private readonly EventDisplayService $display,
    private readonly EventListQueryService $listQuery,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->entityTypeManager = $entityTypeManager;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('bongolava_admin.event_display'),
      $container->get('bongolava_admin.event_list_query'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Liste de tous les événements avec filtres et pagination.
   */
  public function listAll(Request $request): array {
    $filters = [
      'keyword' => trim((string) $request->query->get('keyword', '')),
      'status' => trim((string) $request->query->get('status', '')),
      'type' => trim((string) $request->query->get('type', '')),
      'location' => trim((string) $request->query->get('location', '')),
    ];

    $page = max(0, (int) $request->query->get('page', 0));
    $result = $this->listQuery->search($filters, $page);

    $items = [];
    foreach ($result['items'] as $item) {
      $items[] = $item + [
        'detail_url' => Url::fromRoute('bongolava_admin.event_review', ['node' => $item['id']])->toString(),
      ];
    }

    return [
      '#theme' => 'bongolava_admin_content_list',
      '#list_type' => 'event',
      '#title' => (string) $this->t('Tous les événements'),
      '#filter_form' => $this->formBuilder()->getForm(EventListFiltersForm::class, $filters),
      '#items' => $items,
      '#total' => $result['total'],
      '#empty_message' => (string) $this->t('Aucun événement trouvé.'),
      '#pager' => ['#type' => 'pager'],
      '#attached' => ['library' => ['bongolava_admin/moderation']],
    ];
  }

  /**
   * Liste des événements en attente de validation avec filtres et pagination.
   */
  public function listPending(Request $request): array {
    $filters = [
      'keyword' => trim((string) $request->query->get('keyword', '')),
      'status' => 'pending',
      'type' => trim((string) $request->query->get('type', '')),
      'location' => trim((string) $request->query->get('location', '')),
    ];

    $page = max(0, (int) $request->query->get('page', 0));
    $result = $this->listQuery->search($filters, $page);

    $items = [];
    foreach ($result['items'] as $item) {
      $items[] = $item + [
        'detail_url' => Url::fromRoute('bongolava_admin.event_review', ['node' => $item['id']])->toString(),
      ];
    }

    return [
      '#theme' => 'bongolava_admin_content_list',
      '#list_type' => 'event',
      '#title' => (string) $this->t('Événements en attente de validation'),
      '#filter_form' => $this->formBuilder()->getForm(EventListFiltersForm::class, $filters),
      '#items' => $items,
      '#total' => $result['total'],
      '#empty_message' => (string) $this->t('Aucun événement en attente trouvé.'),
      '#pager' => ['#type' => 'pager'],
      '#attached' => ['library' => ['bongolava_admin/moderation']],
    ];
  }

  /**
   * Page de détail d'un événement pour validation.
   */
  public function review(NodeInterface $node): array {
    $data = $this->display->build($node);
    $status = (string) ($data['status'] ?? '');
    $isPending = $status === 'pending';

    $approveForm = NULL;
    $cancelForm = NULL;
    if ($isPending) {
      $approveForm = $this->formBuilder()->getForm(EventApproveForm::class, $node);
      $cancelForm = $this->formBuilder()->getForm(EventCancelForm::class, $node);
    }

    return [
      '#theme' => 'bongolava_admin_event_detail',
      '#event' => $data,
      '#status_label' => $this->display->statusLabel($status),
      '#is_pending' => $isPending,
      '#approve_form' => $approveForm,
      '#cancel_form' => $cancelForm,
      '#cancel_url' => Url::fromRoute('bongolava_admin.event_cancel', ['node' => $node->id()])->toString(),
      '#back_url' => Url::fromRoute('bongolava_admin.events_list')->toString(),
      '#attached' => ['library' => ['bongolava_admin/moderation']],
    ];
  }

  public function reviewTitle(NodeInterface $node): string {
    return (string) $this->t('Validation événement : @title', ['@title' => $node->label()]);
  }

}
