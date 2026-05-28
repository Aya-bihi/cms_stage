<?php

namespace Drupal\portail_ministre\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Formulaire d'inscription à une formation E-Learning.
 */
class ElearningInscriptionForm extends FormBase {

  public function getFormId() {
    return 'portail_ministre_elearning_inscription_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {
    $form['cours_id'] = [
      '#type'  => 'hidden',
      '#value' => $id,
    ];

    $form['motivation'] = [
      '#type'        => 'textarea',
      '#title'       => $this->t('Motivation / Justification'),
      '#required'    => TRUE,
      '#rows'        => 4,
      '#placeholder' => 'Expliquez pourquoi vous souhaitez suivre cette formation...',
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Confirmer l\'inscription'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db   = Database::getConnection();
    $user = \Drupal::currentUser();
    $id   = $form_state->getValue('cours_id');

    $db->insert('portail_ministre_demande')
      ->fields([
        'id_utilisateur' => $user->id(),
        'type_demande'   => 'ELEARNING',
        'exige_demande'  => 'Inscription formation #' . $id,
        'commentaire'    => $form_state->getValue('motivation'),
        'statut'         => 'EN_ATTENTE',
        'date_creation'  => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    portail_ministre_journal('ELEARNING_INSCRIPTION',
      'Inscription formation #' . $id . ' par ' . $user->getDisplayName());

    \Drupal::messenger()->addMessage(
      $this->t('Votre inscription a été soumise avec succès.')
    );

    $form_state->setRedirect('portail_ministre.elearning');
  }

}