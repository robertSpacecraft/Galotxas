import { describe, expect, it } from 'vitest';
import { isSchoolPath, schoolPath } from './schoolRoutes';

describe('schoolRoutes', () => {
  it('centralizes the public School route and its future descendant matching', () => {
    expect(schoolPath()).toBe('/escuela');
    expect(isSchoolPath('/escuela')).toBe(true);
    expect(isSchoolPath('/escuela/')).toBe(true);
    expect(isSchoolPath('/escuela/alumno')).toBe(true);
    expect(isSchoolPath('/school')).toBe(false);
    expect(isSchoolPath('/academy')).toBe(false);
  });
});
