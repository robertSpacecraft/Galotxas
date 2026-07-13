export const resolveApiBaseUrl = ({ configuredUrl, isDevelopment }) => {
  const normalizedUrl = typeof configuredUrl === 'string' ? configuredUrl.trim() : '';

  if (normalizedUrl) {
    return normalizedUrl;
  }

  return isDevelopment
    ? 'http://localhost:8080/api/v1'
    : '/api/v1';
};
