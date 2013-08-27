CREATE TABLE `checks` (
  `site` varchar(50) NOT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `result` set('core','plugins','themes') NOT NULL
) ENGINE=ARCHIVE DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `sites` (
  `database` varchar(50) NOT NULL,
  `domain` varchar(100) NOT NULL,
  `owner_email` varchar(100) NOT NULL,
  `owner_name` varchar(50) NOT NULL,
  `url` varchar(100) NOT NULL,
  PRIMARY KEY (`database`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;