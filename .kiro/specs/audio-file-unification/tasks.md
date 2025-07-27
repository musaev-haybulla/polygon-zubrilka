# Implementation Plan

- [x] 1. Extend AudioFileHelper class with new methods
  - Add deleteAudioFile() method for single file deletion
  - Add private logFileOperation() method for operation logging
  - Add private safeUnlink() method for safe file deletion with error handling
  - Write unit tests for new methods to ensure proper functionality
  - _Requirements: 1.1, 1.2, 2.1, 4.1, 4.2, 4.3_

- [x] 2. Replace unlink() calls in add_audio_step1.php
  - Replace direct unlink() call on line 189 with AudioFileHelper::deleteAudioFile()
  - Replace direct unlink() call on line 320 with AudioFileHelper::deleteAudioFile()
  - Update error handling to use the new method's return value
  - Test file upload and replacement functionality
  - _Requirements: 1.1, 3.1, 3.2_

- [x] 3. Replace unlink() calls in delete_audio.php
  - Replace direct unlink() calls on lines 41 and 49 with AudioFileHelper::deleteAudioFile()
  - Maintain existing transaction logic while using new unified methods
  - Test complete audio deletion workflow
  - _Requirements: 1.1, 3.1, 3.2_

- [x] 4. Replace unlink() calls in restore_original_audio.php
  - Replace direct unlink() call on line 59 with AudioFileHelper::deleteAudioFile()
  - Ensure proper error handling and transaction rollback on file operation failures
  - Test audio restoration functionality
  - _Requirements: 1.1, 3.1, 3.2_

- [x] 5. Replace unlink() calls in add_audio_step2.php
  - Replace direct unlink() calls on lines 74 and 112 with AudioFileHelper::deleteAudioFile()
  - Update error handling in audio trimming workflow
  - Test audio trimming and error recovery scenarios
  - _Requirements: 1.1, 3.1, 3.2_

- [x] 6. Update existing deleteAudioFiles() method
  - Modify the existing deleteAudioFiles() method to use new safeUnlink() internally
  - Add logging to the existing method for consistency
  - Ensure backward compatibility with current usage
  - _Requirements: 2.2, 3.2, 4.1_

- [ ] 7. Create comprehensive tests for file operations
  - Write integration tests for all modified PHP files
  - Test error scenarios (missing files, permission issues)
  - Verify logging output format and content
  - Test rollback scenarios in transactional contexts
  - _Requirements: 1.3, 2.3, 4.1, 4.2, 4.3_

- [ ] 8. Validate unified implementation
  - Run all modified scripts to ensure no regressions
  - Check log files for proper operation logging
  - Verify error handling works correctly across all use cases
  - Confirm all direct unlink() calls have been replaced
  - _Requirements: 3.1, 3.2, 4.1_