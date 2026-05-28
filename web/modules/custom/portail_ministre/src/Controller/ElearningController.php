<?php

namespace Drupal\portail_ministre\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;

/**
 * Controller E-Learning — Portail Ministère.
 */
class ElearningController extends ControllerBase {

  /**
   * Catalogue des formations.
   */
  public function catalogue() {
    $db = Database::getConnection();

    if (!$db->schema()->tableExists('portail_ministre_cours')) {
      return [
        '#markup' => '<p>La table e-learning n\'est pas encore créée. 
                      Lancez drush updb.</p>',
      ];
    }

    $cours = $db->select('portail_ministre_cours', 'c')
      ->fields('c')
      ->condition('statut', 'ACTIF')
      ->orderBy('date_creation', 'DESC')
      ->execute()
      ->fetchAll();

    return [
      '#theme' => 'elearning_catalogue',
      '#cours' => $cours,
    ];
  }

  /**
   * Détail d'une formation.
   */
  public function detail($id) {
    $db = Database::getConnection();

    $cours = $db->select('portail_ministre_cours', 'c')
      ->fields('c')
      ->condition('id', $id)
      ->execute()
      ->fetchObject();

    if (!$cours) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    return [
      '#markup' => '<h2>' . htmlspecialchars($cours->titre) . '</h2>
                   <p>' . htmlspecialchars($cours->description) . '</p>',
    ];
  }

  /**
   * Vue admin.
   */
  public function admin() {
    return $this->catalogue();
  }

public function liste() {
    return $this->catalogue();
  }
}