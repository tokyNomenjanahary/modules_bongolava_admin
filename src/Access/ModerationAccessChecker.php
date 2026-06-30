<?php

namespace Drupal\bongolava_admin\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;

/**
 * Vérifie l'accès aux pages de modération par type de contenu.
 */
final class ModerationAccessChecker implements AccessInterface {

  /**
   * {@inheritdoc}
   */
  public function access(Route $route, AccountInterface $account, RouteMatchInterface $route_match): AccessResult {
    if (!$account->hasPermission('moderate bongolava content')) {
      return AccessResult::forbidden()->addCacheContexts(['user.permissions']);
    }

    $expectedBundle = (string) $route->getRequirement('_bongolava_moderation_access');
    $node = $route_match->getParameter('node');

    if (!$node instanceof NodeInterface) {
      return AccessResult::forbidden();
    }

    if ($node->bundle() !== $expectedBundle) {
      return AccessResult::forbidden()->addCacheableDependency($node);
    }

    return AccessResult::allowed()->addCacheableDependency($node);
  }

}
