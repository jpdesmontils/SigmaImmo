-- Les annonces déjà présentes restent publiques lors de cette migration.
UPDATE properties SET visibility = 'shared';
