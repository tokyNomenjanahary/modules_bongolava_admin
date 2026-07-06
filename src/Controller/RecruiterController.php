<?php

namespace Drupal\bongolava_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
use Drupal\user\UserInterface;
use Drupal\bongolava_job\Repository\RecruiterRepository;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Contrôleur pour lister les utilisateurs de rôle "recruteur".
 */
final class RecruiterController extends ControllerBase {

  public function __construct(
    private readonly RecruiterRepository $recruiterRepository,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('bongolava_job.recruiter_repository'),
    );
  }

  /**
   * Liste tous les utilisateurs possédant le rôle `recruiter` avec filtres et pagination.
   */
  public function listAll(Request $request = NULL): array {
    $filters = [
      'keyword' => trim((string) ($request?->query->get('keyword', '') ?? '')),
      'status' => trim((string) ($request?->query->get('status', '') ?? '')),
    ];

    $per_page = 20;

    // Build count query.
    $count_query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('roles', 'recruiter');
    if (!empty($filters['status'])) {
      if ($filters['status'] === 'active') {
        $count_query->condition('status', 1);
      }
      elseif ($filters['status'] === 'blocked') {
        $count_query->condition('status', 0);
      }
    }
    if (!empty($filters['keyword'])) {
      $count_query->condition('mail', '%' . $filters['keyword'] . '%', 'LIKE');
    }
    $total = (int) $count_query->count()->execute();

    // Initialize pager
    $pager = \Drupal::service('pager.manager')->createPager($total, $per_page);
    $current_page = $pager->getCurrentPage();
    $offset = $current_page * $per_page;

    // Build main query with range
    $query = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->condition('roles', 'recruiter')
      ->sort('created', 'DESC')
      ->range($offset, $per_page);
    if (!empty($filters['status'])) {
      if ($filters['status'] === 'active') {
        $query->condition('status', 1);
      }
      elseif ($filters['status'] === 'blocked') {
        $query->condition('status', 0);
      }
    }
    if (!empty($filters['keyword'])) {
      $query->condition('mail', '%' . $filters['keyword'] . '%', 'LIKE');
    }

    $uids = $query->execute();
    $users = User::loadMultiple($uids ?: []);

    $header = [
      $this->t('UID'),
      $this->t('Nom'),
      $this->t('Email'),
      $this->t('Statut'),
      $this->t('Actions'),
    ];

    $rows = [];
    foreach ($users as $user) {
      if (!$user instanceof UserInterface) {
        continue;
      }
      $status_label = $user->isActive() ? $this->t('Actif') : $this->t('Bloqué');

      $view_link = Link::fromTextAndUrl($this->t('Voir'), Url::fromRoute('bongolava_admin.recruiter_detail', ['user' => $user->id()]))->toRenderable();
      $view_link['#attributes'] = ['class' => ['button', 'button--small', 'button--primary']];

      $rows[] = [
        'data' => [
          $user->id(),
          $user->getDisplayName(),
          $user->getEmail(),
          $status_label,
          [
            'data' => [$view_link],
          ],
        ],
      ];
    }

    $build = [];
    $filter_form = $this->formBuilder()->getForm(\Drupal\bongolava_admin\Form\RecruiterListFiltersForm::class, $filters);
    $build['filter_form'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bongolava-admin-filters']],
      'form' => $filter_form,
    ];

    if (empty($rows)) {
      $build['empty'] = [
        '#markup' => $this->t('Aucun utilisateur recruteur trouvé.'),
      ];
    }
    else {
      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
    }

    $build['pager'] = ['#type' => 'pager'];
    $build['#attached'] = ['library' => ['bongolava_admin/moderation']];
    $build['#title'] = $this->t('Liste des recruteurs');

    return $build;
  }

  /**
   * Page de détail montrant les données liées au profil recruteur.
   */
  public function detail(UserInterface $user): array {
    $profile = $this->recruiterRepository->loadByUser($user->id());

    $rows = [
      [$this->t('UID'), $user->id()],
      [$this->t('Nom'), $user->getDisplayName()],
      [$this->t('Email'), $user->getEmail()],
      [$this->t('Organisation'), $profile['organization'] ?? ''],
      [$this->t('NIF'), $profile['nif_number'] ?? ''],
      [$this->t('CIN'), $profile['cin_number'] ?? ''],
      [$this->t('Téléphone'), $profile['phone'] ?? ''],
      [$this->t('Adresse'), $profile['address'] ?? ''],
      [$this->t('Site web'), $profile['website'] ?? ''],
      [$this->t('Créé le'), $profile['created_at'] ?? ''],
    //   [$this->t('Mis à jour'), $profile['updated_at'] ?? ''],
    ];

    $build = [
      '#type' => 'table',
      '#rows' => $rows,
      '#title' => $this->t('Détails recruteur : @name', ['@name' => $user->getDisplayName()]),
    //   'back' => [
    //     '#type' => 'link',
    //     '#title' => $this->t('Retour à la liste'),
    //     '#url' => Url::fromRoute('bongolava_admin.recruiters_list'),
    //     '#attributes' => ['class' => ['button']],
    //   ],
    ];

    // Build the FAPI form for toggling status and attach it to the page.
    $form = $this->formBuilder()->getForm(\Drupal\bongolava_admin\Form\RecruiterToggleStatusForm::class, $user->id());

    // Render the form to HTML to avoid any theme nesting issues inside the table cell.
    $rendered_form = \Drupal::service('renderer')->renderRoot($form);

    // Also add the toggle form as a visible row in the details table for clarity.
    $build['#rows'][] = [
      'data' => [
        $this->t('Actions'),
        Markup::create($rendered_form),
      ],
    ];
    $build['#attached'] = ['library' => ['bongolava_admin/moderation']];

    return $build;
  }

  /**
   * Basculer l'état actif/bloqué d'un utilisateur recruteur.
   */
  public function toggle(UserInterface $user, Request $request): RedirectResponse {
    // Validate CSRF token from POST.
    $token = (string) $request->request->get('token', '');
    $csrf_service = \Drupal::service('csrf_token');
    if (!$csrf_service->validate($token, 'bongolava_admin.recruiter_toggle:' . $user->id())) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    if ($user->isActive()) {
      $user->block();
      $this->messenger()->addStatus($this->t('Le compte @name a été désactivé.', ['@name' => $user->getDisplayName()]));
    }
    else {
      $user->activate();
      $this->messenger()->addStatus($this->t('Le compte @name a été activé.', ['@name' => $user->getDisplayName()]));
    }
    $user->save();

    // Redirect to the recruiters list after the action.
    return new RedirectResponse(Url::fromRoute('bongolava_admin.recruiters_list')->toString());
  }

  public function detailTitle(User $user): string {
    return (string) $this->t('Recruteur : @name', ['@name' => $user->getDisplayName()]);
  }

}
