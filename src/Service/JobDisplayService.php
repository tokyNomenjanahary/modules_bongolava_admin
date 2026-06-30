<?php

namespace Drupal\bongolava_admin\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Prépare les données d'affichage d'une offre d'emploi pour la modération.
 */
final class JobDisplayService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
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
      'company' => $this->fieldValue($node, 'field_company'),
      'location' => $this->termLabel($node, 'field_localisation'),
      'contract_type' => $this->termLabel($node, 'field_type_contrat'),
      'sector' => $this->termLabel($node, 'field_secteur'),
      'salary' => $this->fieldValue($node, 'field_salary'),
      'description' => $this->fieldValue($node, 'field_description_event'),
      'requirements' => $this->fieldValue($node, 'field_requirements'),
      'responsibilities' => $this->fieldValue($node, 'field_responsibilities'),
      'contact_email' => $this->fieldValue($node, 'field_contact_email'),
      'contact_phone' => $this->fieldValue($node, 'field_contact_phone'),
      'is_urgent' => (bool) $node->get('field_urgent')->value,
      'is_remote' => (bool) $node->get('field_remote')->value,
      'user_type' => $this->fieldValue($node, 'field_user_type'),
      'status' => $this->fieldValue($node, 'field_status_offre'),
      'cancellation_reason' => $this->fieldValue($node, 'field_raison_annulation'),
      'expires_at' => $this->fieldValue($node, 'field_expires_at'),
      'views_count' => (int) ($node->get('field_views_count')->value ?? 0),
      'created_at' => $this->formatDate((int) $node->getCreatedTime()),
      'owner' => $ownerLabel,
      'image_url' => $this->imageUrl($node),
    ];
  }

  public function statusLabel(string $status): string {
    return match ($status) {
      'pending' => 'En attente',
      'published' => 'Publiée',
      'rejected' => 'Rejetée',
      'expired' => 'Expirée',
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

  private function imageUrl(NodeInterface $node): ?string {
    if (!$node->hasField('field_image_offre') || $node->get('field_image_offre')->isEmpty()) {
      return NULL;
    }

    try {
      $mediaId = (int) ($node->get('field_image_offre')->target_id ?? 0);
      if ($mediaId <= 0) {
        return NULL;
      }
      $media = $this->entityTypeManager->getStorage('media')->load($mediaId);
      if (!$media) {
        return NULL;
      }
      $fid = (int) ($media->get('field_media_image')->target_id ?? 0);
      if ($fid <= 0) {
        return NULL;
      }
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      if (!$file) {
        return NULL;
      }
      return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
    }
    catch (\Throwable) {
      return NULL;
    }
  }

}
