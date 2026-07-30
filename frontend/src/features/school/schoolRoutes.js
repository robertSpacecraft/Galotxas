export const schoolPath = () => '/escuela';

export const isSchoolPath = (pathname) => (
  /^\/escuela(?:\/.*)?$/.test(pathname)
);
