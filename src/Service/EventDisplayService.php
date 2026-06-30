<?php

namespace Drupal\bongolava_admin\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Prépare les données d'affichage d'un événement pour la modération.
 */
final class EventDisplayService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @return array<string, mixed>
   */
  public function build(NodeInterface $node): array {
    $owner = $node->getOwner();
    $ownerLabel = $owner instanceof UserInterface ? $owner->getDisplayName() : '';

    return [
      'id' => (int) $node->id(),
      'title' => $node->label(),
      'type' => $this->termLabel($node, 'field_type_evenement'),
      'date' => $this->fieldValue($node, 'field_date_debut'),
      'horaires' => $this->fieldValue($node, 'field_horaires'),
      'location' => $this->termLabel($node, 'field_localisation'),
      'address' => $this->fieldValue($node, 'field_address'),
      'description' => $this->fieldValue($node, 'field_description'),
      'capacity' => $node->hasField('field_capacity') && !$node->get('field_capacity')->isEmpty()
        ? (int) $node->get('field_capacity')->value
        : NULL,
      'registered' => $this->countRegistrations((int) $node->id()),
      'organizer' => $this->fieldValue($node, 'field_organizer'),
      'contact_email' => $this->fieldValue($node, 'field_contact_email'),
      'contact_phone' => $this->fieldValue($node, 'field_contact_phone'),
      'status' => $this->fieldValue($node, 'field_status'),
      'cancellation_reason' => $this->fieldValue($node, 'field_raison_annulation'),
      'created_at' => $this->formatDate((int) $node->getCreatedTime()),
      'owner' => $ownerLabel,
    ];
  }

  public function statusLabel(string $status): string {
    return match ($status) {
      'pending' => 'En attente',
      'published' => 'Publié',
      'cancelled' => 'Annulé',
      'finished' => 'Terminé',
      default => $status !== '' ? $status : '—',
    };
  }

  private function fieldValue(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return (string) $node->get($field)->value;
  }

  private function termLabel(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    return (string) ($node->get($field)->entity?->label() ?? '');
  }

  private function formatDate(int $timestamp): string {
    if ($timestamp <= 0) {
      return '—';
    }
    return \Drupal::service('date.formatter')->format($timestamp, 'custom', 'd/m/Y H:i');
  }

  private function countRegistrations(int $eventId): int {
    return (int) $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'inscription_evenement')
      ->condition('field_evenement_ref', $eventId)
      ->count()
      ->execute();
  }

}
