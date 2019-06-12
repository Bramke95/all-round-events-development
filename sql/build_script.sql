-- MySQL Script generated by MySQL Workbench
-- Fri Jun  7 12:30:06 2019
-- Model: New Model    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Schema mydb
-- -----------------------------------------------------
CREATE SCHEMA IF NOT EXISTS `mydb` DEFAULT CHARACTER SET utf8 ;
USE `mydb` ;

-- -----------------------------------------------------
-- Table `mydb`.`users`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`users` (
  `Id_Users` INT NULL AUTO_INCREMENT,
  `email` VARCHAR(50) NULL DEFAULT NULL,
  `pass` VARCHAR(100) NULL DEFAULT NULL,
  `salt` VARCHAR(100) NULL DEFAULT NULL,
  `is_admin` INT NULL DEFAULT NULL,
  UNIQUE INDEX `E-mail_UNIQUE` (`email` ASC),
  PRIMARY KEY (`Id_Users`),
  UNIQUE INDEX `Id_Users_UNIQUE` (`Id_Users` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`educations`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`educations` (
  `ideducations_id` INT NOT NULL AUTO_INCREMENT,
  `from_date` VARCHAR(15) NULL DEFAULT NULL,
  `to_date` VARCHAR(15) NULL DEFAULT NULL,
  `school` VARCHAR(45) NULL DEFAULT NULL,
  `education` VARCHAR(45) NULL DEFAULT NULL,
  `percentage` INT NULL DEFAULT NULL,
  `users_Id_Users` INT NOT NULL,
  UNIQUE INDEX `ideducations_id_UNIQUE` (`ideducations_id` ASC),
  PRIMARY KEY (`ideducations_id`),
  INDEX `fk_educations_users_idx` (`users_Id_Users` ASC),
  CONSTRAINT `fk_educations_users`
    FOREIGN KEY (`users_Id_Users`)
    REFERENCES `mydb`.`users` (`Id_Users`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`language`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`language` (
  `language_id` INT NOT NULL AUTO_INCREMENT,
  `language` VARCHAR(45) NULL DEFAULT NULL,
  `speaking` VARCHAR(45) NULL DEFAULT NULL,
  `writing` VARCHAR(45) NULL DEFAULT NULL,
  `reading` VARCHAR(45) NULL DEFAULT NULL,
  `users_Id_Users` INT NOT NULL,
  PRIMARY KEY (`language_id`),
  UNIQUE INDEX `language_id_UNIQUE` (`language_id` ASC),
  INDEX `fk_language_users1_idx` (`users_Id_Users` ASC),
  CONSTRAINT `fk_language_users1`
    FOREIGN KEY (`users_Id_Users`)
    REFERENCES `mydb`.`users` (`Id_Users`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`expierence`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`expierence` (
  `idexpierence` INT NOT NULL AUTO_INCREMENT,
  `compamy` VARCHAR(45) NULL DEFAULT NULL,
  `jobtitle` VARCHAR(45) NULL DEFAULT NULL,
  `from` VARCHAR(45) NULL DEFAULT NULL,
  `to` VARCHAR(45) NULL DEFAULT NULL,
  `users_Id_Users` INT NOT NULL,
  PRIMARY KEY (`idexpierence`),
  UNIQUE INDEX `idexpierence_UNIQUE` (`idexpierence` ASC),
  INDEX `fk_expierence_users1_idx` (`users_Id_Users` ASC),
  CONSTRAINT `fk_expierence_users1`
    FOREIGN KEY (`users_Id_Users`)
    REFERENCES `mydb`.`users` (`Id_Users`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`hashess`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`hashess` (
  `idhashess_id` INT NOT NULL AUTO_INCREMENT,
  `HASH` VARCHAR(150) NULL DEFAULT NULL,
  `Type` INT NULL DEFAULT NULL,
  `last_used` VARCHAR(45) NULL DEFAULT NULL,
  `begin_date` VARCHAR(45) NULL DEFAULT NULL,
  `end_date` VARCHAR(45) NULL DEFAULT NULL,
  `users_Id_Users` INT NOT NULL,
  PRIMARY KEY (`idhashess_id`),
  UNIQUE INDEX `idhashess_id_UNIQUE` (`idhashess_id` ASC),
  INDEX `fk_hashess_users1_idx` (`users_Id_Users` ASC),
  CONSTRAINT `fk_hashess_users1`
    FOREIGN KEY (`users_Id_Users`)
    REFERENCES `mydb`.`users` (`Id_Users`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`Activation_codes`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`Activation_codes` (
  `idActivation_codes` INT NOT NULL AUTO_INCREMENT,
  `code` INT NULL DEFAULT NULL,
  `amount` INT NULL DEFAULT NULL,
  `closed` INT NULL DEFAULT NULL,
  PRIMARY KEY (`idActivation_codes`),
  UNIQUE INDEX `idActivation_codes_UNIQUE` (`idActivation_codes` ASC),
  UNIQUE INDEX `code_UNIQUE` (`code` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `mydb`.`users_data`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `mydb`.`users_data` (
  `name` VARCHAR(50) NULL DEFAULT NULL,
  `date_of_birth` DATETIME NULL DEFAULT NULL,
  `Gender` INT NULL DEFAULT NULL,
  `adres_line_one` VARCHAR(100) NULL DEFAULT NULL,
  `adres_line_two` VARCHAR(100) NULL DEFAULT NULL,
  `driver_license` VARCHAR(10) NULL DEFAULT NULL,
  `nationality` VARCHAR(25) NULL DEFAULT NULL,
  `telephone` VARCHAR(45) NULL DEFAULT NULL,
  `marital_state` INT NULL DEFAULT NULL,
  `text` VARCHAR(2000) NULL DEFAULT NULL,
  `users_Id_Users` INT NOT NULL,
  INDEX `fk_users_data_users1_idx` (`users_Id_Users` ASC),
  CONSTRAINT `fk_users_data_users1`
    FOREIGN KEY (`users_Id_Users`)
    REFERENCES `mydb`.`users` (`Id_Users`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
