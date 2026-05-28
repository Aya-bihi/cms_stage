<?php

namespace Drupal\portail_ministre\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;

/**
 * Controller Gestion du Courrier — Portail Ministère.
 */
class CourrierController extends ControllerBase {

  /**
   * Liste des courriers.
   */
  public function liste() {
    $db = Database::getConnection();

    // Vérifier si la table courrier existe
    if (!$db->schema()->tableExists('portail_ministre_courrier')) {
      return [
        '#markup' => '<p>La table courrier n\'est pas encore créée. 
                      Lancez drush updb.</p>',
      ];
    }

    $courriers = $db->select('portail_ministre_courrier', 'c')
      ->fields('c')
      ->orderBy('date_reception', 'DESC')
      ->execute()
      ->fetchAll();

    return [
      '#theme'    => 'courrier_liste',
      '#courriers' => $courriers,
      '#type'     => 'TOUS',
    ];
  }

  /**
   * Détail d'un courrier.
   */
  public function detail($id) {
    $db = Database::getConnection();

    $courrier = $db->select('portail_ministre_courrier', 'c')
      ->fields('c')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$courrier) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    return [
      '#markup' => '<h2>' . htmlspecialchars($courrier->objet) . '</h2>
                   <p><strong>Type :</strong> ' . $courrier->type_courrier . '</p>
                   <p><strong>Expéditeur :</strong> ' . htmlspecialchars($courrier->expediteur) . '</p>
                   <p><strong>Statut :</strong> ' . $courrier->statut . '</p>',
    ];
  }

  /**
   * Vue admin.
   */
  public function admin() {
    return $this->liste();
  }

}