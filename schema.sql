CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(256) NOT NULL,
  `email` varchar(320) NOT NULL,
  `validts` int unsigned NOT NULL DEFAULT '0',
  `checked` tinyint NOT NULL DEFAULT '0',
  `valid` tinyint NOT NULL DEFAULT '0',
  `confirmed` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_uindex` (`email`),
  UNIQUE KEY `users_username_uindex` (`username`),
  UNIQUE KEY `users_id_uindex` (`id`),
  KEY `users_valid_index` (`valid`),
  KEY `users_checked_index` (`checked`),
  KEY `users_confirmed_index` (`confirmed`),
  KEY `users_validts_index` (`validts`)
) ENGINE=InnoDB AUTO_INCREMENT=440001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
