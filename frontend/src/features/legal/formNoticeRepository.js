import noticeArtifact from '../../generated/legal/form-notices.json';

export const minorPublicIdentityNotice = (() => {
  const notice = noticeArtifact?.notices?.find(
    (candidate) => candidate.id === 'NOTICE-PUBLIC-IDENTITY-MINORS',
  );

  if (
    noticeArtifact?.schemaVersion !== 1
    || noticeArtifact?.notices?.length !== 3
    || notice?.status !== 'vigente'
    || notice?.scope !== 'public_competition_identity'
  ) {
    return null;
  }

  return notice;
})();

export const contactFormNotice = (() => {
  const notice = noticeArtifact?.notices?.find(
    (candidate) => candidate.id === 'NOTICE-CONTACT-FORM',
  );

  if (
    noticeArtifact?.schemaVersion !== 1
    || noticeArtifact?.notices?.length !== 3
    || notice?.status !== 'vigente'
    || notice?.scope !== 'contact_request'
    || notice?.privacyUrl !== '/legal/privacidad'
  ) {
    return null;
  }

  return notice;
})();

export const schoolEnrollmentNotice = (() => {
  const notice = noticeArtifact?.notices?.find(
    (candidate) => candidate.id === 'NOTICE-SCHOOL-ENROLLMENT',
  );

  if (
    noticeArtifact?.schemaVersion !== 1
    || noticeArtifact?.notices?.length !== 3
    || notice?.status !== 'vigente'
    || notice?.scope !== 'school_enrollment'
    || notice?.privacyUrl !== '/legal/privacidad'
  ) {
    return null;
  }

  return notice;
})();
