USE `demo`;

DROP TABLE IF EXISTS `sample`;

CREATE TABLE `sample` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `title` NVARCHAR(100) NOT NULL,
  `author` NVARCHAR(50) NOT NULL,
  `text` NVARCHAR(255) NOT NULL,
  `disabled` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `ix_title` (`title` ASC, `author` ASC),
  INDEX `ix_author` (`author` ASC)
)
ENGINE = InnoDB;

DROP TABLE IF EXISTS `sample_ngram`;

CREATE TABLE `sample_ngram` (
  `id` INT NOT NULL,
  `title` NVARCHAR(100) NOT NULL,
  `author` NVARCHAR(50) NOT NULL,
  `text` NVARCHAR(255) NOT NULL,
  `text_bigram` NVARCHAR(761) NOT NULL,
  `disabled` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `ix_title` (`title` ASC, `author` ASC),
  INDEX `ix_author` (`author` ASC),
  FULLTEXT INDEX `fx_text_bigram`(`text_bigram`) WITH PARSER ngram
)
ENGINE = InnoDB COLLATE `utf8_unicode_ci`;

DROP FUNCTION IF EXISTS `func_get_bigram`;

DELIMITER //
CREATE FUNCTION func_get_bigram(str NVARCHAR(255))
RETURNS NVARCHAR(761) DETERMINISTIC
BEGIN
    DECLARE _buf NVARCHAR(761) DEFAULT '';
    DECLARE max, p INT DEFAULT 0;
    SET max = CHAR_LENGTH(str);
    congr: LOOP
      SET p = p + 1;
      IF p >= max THEN LEAVE congr; END IF;
      IF CHAR_LENGTH(_buf) > 0 THEN SET _buf = CONCAT(_buf, ' '); END IF;
      SET _buf = CONCAT(_buf, SUBSTR(str, p, 2));
    END LOOP congr;
    RETURN _buf;
END
//
DELIMITER ;

/*
INSERT INTO `sample_ngram`(`id`, `title`, `author`, `text`, `text_bigram`) SELECT s.id, s.title, s.author, s.text, func_get_bigram(s.text) FROM `sample` AS s;
*/
