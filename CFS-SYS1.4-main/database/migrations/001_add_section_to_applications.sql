-- =====================================================================
-- Migration 001: Student Masterlist - section assignment
-- Adds ONE nullable column to enrollment_applications. Nothing is dropped,
-- recreated or deleted; existing rows simply get section_name = NULL
-- (the Masterlist shows that as "Unassigned").
-- Safe to run more than once (needs MariaDB 10.0.2+; XAMPP ships 10.4+).
--
-- Why here: an application is already unique per (student, school year),
-- so a section stored on it is automatically tied to the right student AND
-- the right school year, and can never create a duplicate student record.
--
-- phpMyAdmin: click database "cfs_sis" in the left list -> SQL tab ->
-- paste this whole file -> Go.   (Do NOT use the Import tab on
-- cfs_sis_mariadb.sql: that file wipes the database.)
-- =====================================================================
USE cfs_sis;

ALTER TABLE enrollment_applications
  ADD COLUMN IF NOT EXISTS section_name VARCHAR(50) NULL AFTER grade_level;
