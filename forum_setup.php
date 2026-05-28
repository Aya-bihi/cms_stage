<?php
\Drupal\node\Entity\NodeType::create([
  'type' => 'forum_sujet',
  'name' => 'Forum Sujet',
  'description' => 'Sujet de discussion du forum',
])->save();

\Drupal\node\Entity\NodeType::create([
  'type' => 'forum_reponse',
  'name' => 'Forum Reponse',
  'description' => 'Reponse a un sujet du forum',
])->save();

echo 'Content types crees avec succes!';