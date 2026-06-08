-- Remove duplicate screening_results rows (keep highest score per application)
DELETE sr FROM screening_results sr
INNER JOIN screening_results sr2
    ON sr.application_id = sr2.application_id
    AND sr.total_score < sr2.total_score;

-- In case of exact score ties, keep the lowest id
DELETE sr FROM screening_results sr
INNER JOIN screening_results sr2
    ON sr.application_id = sr2.application_id
    AND sr.id > sr2.id;

-- Now enforce uniqueness
ALTER TABLE screening_results
    ADD UNIQUE KEY uq_screening_application (application_id);
