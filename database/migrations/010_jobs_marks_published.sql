-- Add marks_published flag: company controls when exam scores are visible to candidates
ALTER TABLE jobs ADD COLUMN IF NOT EXISTS marks_published TINYINT(1) DEFAULT 0 AFTER icp_confirmed;
