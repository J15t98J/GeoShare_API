CREATE DATABASE `geoshare`;

CREATE TABLE `devices` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(10) NOT NULL,
  `key` varchar(100) NOT NULL,
  `user` int(11) NOT NULL,
  `regdate` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID_UNIQUE` (`ID`),
  UNIQUE KEY `key_UNIQUE` (`key`),
  KEY `devices_user_idx` (`user`),
  CONSTRAINT `devices_user` FOREIGN KEY (`user`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `friendrequests` (
  `ID` int(20) NOT NULL AUTO_INCREMENT,
  `from_ID` int(11) NOT NULL,
  `to_ID` int(11) NOT NULL,
  `sent` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `ID_UNIQUE` (`ID`),
  KEY `from_ID_frq_idx` (`from_ID`),
  KEY `to_ID_frq_idx` (`to_ID`),
  CONSTRAINT `from_ID_frq` FOREIGN KEY (`from_ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `to_ID_frq` FOREIGN KEY (`to_ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `friendships` (
  `ID` int(20) NOT NULL AUTO_INCREMENT,
  `ID_A` int(11) NOT NULL,
  `ID_B` int(11) NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `uq_friendship` (`ID_A`,`ID_B`),
  UNIQUE KEY `ID_UNIQUE` (`ID`),
  KEY `ID_A_idx` (`ID_A`),
  KEY `ID_B_idx` (`ID_B`),
  CONSTRAINT `ID_A` FOREIGN KEY (`ID_A`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `ID_B` FOREIGN KEY (`ID_B`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sessions` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `pID` varchar(100) NOT NULL,
  `user` int(11) NOT NULL,
  `IP` varchar(50) DEFAULT NULL,
  `created` datetime NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `pID_UNIQUE` (`pID`),
  KEY `user_idx` (`user`),
  CONSTRAINT `user` FOREIGN KEY (`user`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `shares` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `pID` varchar(100) NOT NULL,
  `type` varchar(5) NOT NULL,
  `from_ID` int(11) NOT NULL,
  `to_ID` int(11) NOT NULL,
  `long` decimal(9,6) NOT NULL,
  `lat` decimal(8,6) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `seen` tinyint(1) unsigned zerofill NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `pID_UNIQUE` (`pID`),
  KEY `from_ID_idx` (`from_ID`),
  KEY `to_ID_idx` (`to_ID`),
  CONSTRAINT `from_ID` FOREIGN KEY (`from_ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `to_ID` FOREIGN KEY (`to_ID`) REFERENCES `users` (`ID`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(25) NOT NULL,
  `email` varchar(45) NOT NULL,
  `pass_hash` varchar(60) NOT NULL,
  `created` datetime NOT NULL,
  `modified` datetime DEFAULT NULL,
  `pic_uri` varchar(36) NOT NULL,
  `findByEmail` tinyint(1) NOT NULL DEFAULT '0',
  `seenTutorial` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `email_UNIQUE` (`email`),
  UNIQUE KEY `username_UNIQUE` (`username`),
  UNIQUE KEY `pic_uri_UNIQUE` (`pic_uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
