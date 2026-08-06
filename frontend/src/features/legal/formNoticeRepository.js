import noticeArtifact from '../../generated/legal/form-notices.json';

export const minorPublicIdentityNotice = (() => {
  const notice = noticeArtifact?.notices?.find(
    (candidate) => candidate.id === 'NOTICE-PUBLIC-IDENTITY-MINORS',
  );

  if (
    noticeArtifact?.schemaVersion !== 1
    || noticeArtifact?.notices?.length !== 1
    || notice?.status !== 'vigente'
    || notice?.scope !== 'public_competition_identity'
  ) {
    return null;
  }

  return notice;
})();
