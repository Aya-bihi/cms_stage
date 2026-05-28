<?php
namespace Drupal\portail_ministre\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

/**
 * Contrôleur du Forum – Portail Ministre
 */
class ForumController extends ControllerBase {

  /**
   * Liste tous les sujets du forum
   */
  public function listeSujets() {
    $query = \Drupal::entityQuery('node')
      ->condition('type', 'forum_sujet')
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->sort('created', 'DESC');
    $nids = $query->execute();
    $nodes = Node::loadMultiple($nids);

    $sujets = [];
    foreach ($nodes as $node) {
      $nb_reponses = \Drupal::entityQuery('node')
        ->condition('type', 'forum_reponse')
        ->condition('field_sujet_parent', $node->id())
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->count()
        ->execute();

      $sujets[] = [
        'id'              => $node->id(),
        'titre'           => $node->label(),
        'auteur'          => $node->uid->entity->getDisplayName(),
        'date'            => date('d/m/Y à H:i', $node->created->value),
        'nb_reponses'     => $nb_reponses,
        'categorie_label' => $node->field_categorie_forum->value ?? 'Général',
        'categorie_value' => $node->field_categorie_forum->value ?? 'general',
      ];
    }

    return [
      '#theme'  => 'forum_list',
      '#sujets' => $sujets,
      '#user'   => \Drupal::currentUser(),
      '#cache'  => ['max-age' => 0],
    ];
  }

  /**
   * Détail d'un sujet + ses réponses
   */
  public function detailSujet($nid) {
    $sujet = Node::load($nid);
    if (!$sujet || $sujet->bundle() !== 'forum_sujet') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $reponses_ids = \Drupal::entityQuery('node')
      ->condition('type', 'forum_reponse')
      ->condition('field_sujet_parent', $nid)
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->sort('created', 'ASC')
      ->execute();

    return [
      '#theme'    => 'forum_detail',
      '#sujet'    => $sujet,
      '#reponses' => Node::loadMultiple($reponses_ids),
      '#user'     => \Drupal::currentUser(),
      '#cache'    => ['max-age' => 0],
    ];
  }

  /**
   * Titre dynamique du sujet
   */
  public function titreSujet($nid) {
    $sujet = Node::load($nid);
    return $sujet ? $sujet->label() : 'Sujet introuvable';
  }
}