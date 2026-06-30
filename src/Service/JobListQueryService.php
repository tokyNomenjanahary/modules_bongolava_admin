<?php

namespace Drupal\bongolava_admin\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Requêtes filtrées et paginées pour les offres d'emploi.
 */
final class JobListQueryService {

  public const PER_PAGE = 20;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly JobDisplayService $display,
    private readonly PagerManagerInterface $pagerManager,
  ) {}

  /**
   * @param array<string, string> $filters
   *
   * @return array{items: array<int, array<string, mixed>>, total: int}
   */
  public function search(array $filters, int $page = 0): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'offre_emploi');

    $this->applyFilters($query, $filters);

    $countQuery = clone $query;
    $total = (int) $countQuery->count()->execute();

    $query->sort('created', 'DESC');
    $query->range($page * self::PER_PAGE, self::PER_PAGE);
    $nids = $query->execute();

    $this->pagerManager->createPager($total, self::PER_PAGE);

    $items = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      if (!$node instanceof NodeInterface) {
        continue;
      }
      $data = $this->display->build($node);
      $items[] = [
        'id' => $data['id'],
        'title' => $data['title'],
        'company' => $data['company'],
        'location' => $data['location'],
        'sector' => $data['sector'],
        'contract_type' => $data['contract_type'],
        'user_type' => $data['user_type'],
        'status' => $data['status'],
        'status_label' => $this->display->statusLabel((string) $data['status']),
        'created_at' => $data['created_at'],
      ];
    }

    return ['items' => $items, 'total' => $total];
  }

  /**
   * @return array<string, string>
   */
  public function statusOptions(): array {
    return [
      '' => 'Tous les statuts',
      'pending' => 'En attente',
      'published' => 'Publiée',
      'rejected' => 'Rejetée',
      'expired' => 'Expirée',
    ];
  }

  /**
   * @return array<string, string>
   */
  public function userTypeOptions(): array {
    return [
      '' => 'Tous les types',
      'recruiter' => 'Recruteur',
      'partenaire' => 'Partenaire',
    ];
  }

  /**
   * @return array<string, string>
   */
  public function taxonomyOptions(string $vocabulary): array {
    $options = ['' => 'Tous'];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $vocabulary]);
    foreach ($terms as $term) {
      $options[$term->label()] = $term->label();
    }
    asort($options);
    return $options;
  }

  /**
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   * @param array<string, string> $filters
   */
  private function applyFilters($query, array $filters): void {
    $keyword = trim($filters['keyword'] ?? '');
    if ($keyword !== '') {
      $group = $query->orConditionGroup()
        ->condition('title', '%' . $keyword . '%', 'LIKE')
        ->condition('field_company', '%' . $keyword . '%', 'LIKE')
        ->condition('field_description_event', '%' . $keyword . '%', 'LIKE');
      $query->condition($group);
    }

    $status = trim($filters['status'] ?? '');
    if ($status !== '') {
      $query->condition('field_status_offre', $status);
    }

    $userType = trim($filters['user_type'] ?? '');
    if ($userType !== '') {
      $query->condition('field_user_type', $userType);
    }

    $this->applyTermFilter($query, 'field_secteur', 'secteur', $filters['sector'] ?? '');
    $this->applyTermFilter($query, 'field_type_contrat', 'type_contrat', $filters['contract_type'] ?? '');
    $this->applyTermFilter($query, 'field_localisation', 'localisation', $filters['location'] ?? '');
  }

  private function applyTermFilter($query, string $field, string $vocabulary, string $label): void {
    $label = trim($label);
    if ($label === '') {
      return;
    }
    $tid = $this->resolveTermId($vocabulary, $label);
    if ($tid) {
      $query->condition($field, $tid);
    }
  }

  private function resolveTermId(string $vocabulary, string $name): ?int {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $vocabulary, 'name' => $name]);
    if ($terms) {
      return (int) reset($terms)->id();
    }
    return NULL;
  }

}
