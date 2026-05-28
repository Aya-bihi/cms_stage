<?php

namespace Drupal\portail_ministre\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Formulaire d'enregistrement d'un courrier.
 */
class CourrierForm extends FormBase {

  public function getFormId() {
    return 'portail_ministre_courrier_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['type_courrier'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Type de courrier'),
      '#required'      => TRUE,
      '#default_value' => 'ARRIVEE',
      '#options'       => [
        'ARRIVEE' => 'Courrier arrivée',
        'DEPART'  => 'Courrier départ',
      ],
    ];

    $form['expediteur'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Expéditeur / Destinataire'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
      '#placeholder' => 'Nom de l\'expéditeur ou destinataire',
    ];

    $form['objet'] = [
      '#type'        => 'textfield',
      '#title'       => $this->t('Objet du courrier'),
      '#required'    => TRUE,
      '#maxlength'   => 255,
    ];

    $form['date_reception'] = [
      '#type'     => 'date',
      '#title'    => $this->t('Date de réception / envoi'),
      '#required' => TRUE,
    ];

    $form['commentaire'] = [
      '#type'  => 'textarea',
      '#title' => $this->t('Observations'),
      '#rows'  => 4,
    ];

    $form['fichier'] = [
      '#type'              => 'managed_file',
      '#title'             => $this->t('Scan du courrier (optionnel)'),
      '#upload_location'   => 'public://courrier/',
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf jpg jpeg png'],
        'file_validate_size'       => [10 * 1024 * 1024],
      ],
    ];

    $form['submit'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Enregistrer le courrier'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $db   = Database::getConnection();
    $user = \Drupal::currentUser();
    $type = $form_state->getValue('type_courrier');
    $numero = portail_ministre_generer_numero_courrier($type);

    $fichier_uri = NULL;
    $fids = $form_state->getValue('fichier');
    if (!empty($fids)) {
      $file = \Drupal\file\Entity\File::load(reset($fids));
      if ($file) {
        $file->setPermanent();
        $file->save();
        $fichier_uri = $file->getFileUri();
      }
    }

    // Utiliser portail_ministre_demande pour le courrier
    $id = $db->insert('portail_ministre_demande')
      ->fields([
        'id_utilisateur' => $user->id(),
        'type_demande'   => 'COURRIER_' . $type,
        'numero_ticket'  => $numero,
        'exige_demande'  => $form_state->getValue('objet'),
        'commentaire'    => 'Expéditeur: ' . $form_state->getValue('expediteur') .
                            "\n" . $form_state->getValue('commentaire'),
        'piece_jointe'   => $fichier_uri,
        'statut'         => 'EN_ATTENTE',
        'date_creation'  => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    portail_ministre_journal('COURRIER_CREATE',
      'Courrier ' . $numero . ' enregistré par ' . $user->getDisplayName(), $id);

    \Drupal::messenger()->addMessage(
      $this->t('Courrier @num enregistré avec succès.', ['@num' => $numero])
    );

    $form_state->setRedirect('portail_ministre.courrier');
  }

}