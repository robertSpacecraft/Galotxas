export const getPublicCompetitionDisplayName = (entry) => {
  const displayName = typeof entry?.public_display_name === 'string'
    ? entry.public_display_name.trim()
    : '';

  return displayName || 'Participante';
};
