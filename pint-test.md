# Pint Formatting Test Report
*Generated: lun. 01 juin 2026 20:04:36 WAT*


  .....⨯...⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯....⨯.⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯⨯............⨯..

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ................................................................................................................................................. 57 files, 30 style issues  
  ⨯ src/Collections/LogDateCollection.php                                                                                                      function_declaration, no_unused_imports  
  ⨯ src/Collections/LogFileInfoCollection.php                                                                                                                        no_unused_imports  
  ⨯ src/Collections/LogRecordCollection.php                                                           function_declaration, no_superfluous_phpdoc_tags, phpdoc_trim, no_unused_imports  
  ⨯ src/Contracts/LoggerInterface.php                                                                                                                                     phpdoc_align  
  ⨯ src/Directives/LoggerCleanDirective.php                                                               concat_space, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Logger.php                                                                                  class_attributes_separation, braces_position, single_line_empty_body, phpdoc_align  
  ⨯ src/Records/LogQueryRecord.php                                                                                               braces_position, single_line_empty_body, phpdoc_align  
  ⨯ src/Services/LogBufferService.php                                                                                                  not_operator_with_successor_space, phpdoc_align  
  ⨯ src/Services/LogCleanerService.php             concat_space, braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement, phpdoc_align  
  ⨯ src/Services/LogPathService.php                                                         concat_space, not_operator_with_successor_space, blank_line_before_statement, phpdoc_align  
  ⨯ src/Services/LogSerializerService.php                      class_attributes_separation, concat_space, not_operator_with_successor_space, blank_line_before_statement, phpdoc_align  
  ⨯ src/Tasks/QueryLogsTask.php                                            braces_position, no_unused_imports, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/Tasks/StreamLogsTask.php                                                              braces_position, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/Tasks/WriteLogTask.php                                             braces_position, phpdoc_separation, not_operator_with_successor_space, single_line_empty_body, phpdoc_align  
  ⨯ src/ValueObjects/IsoZuluTime.php                        class_attributes_separation, concat_space, phpdoc_separation, no_unused_imports, blank_line_before_statement, phpdoc_align  
  ⨯ tests/Feature/LoggerBufferIntegrationTest.php                                                               concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ tests/Feature/LoggerCleanerIntegrationTest.php        class_attributes_separation, concat_space, no_unused_imports, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ tests/Feature/LoggerIntegrationTest.php                                                                     concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ tests/Fixtures/Enums/TestUserRole.php                                                                                                                           no_empty_statement  
  ⨯ tests/Integration/Config/LoggerConfigTest.php                                                                                         class_attributes_separation, ordered_imports  
  ⨯ tests/Integration/LoggerServiceProviderTest.php                                                                                                                    ordered_imports  
  ⨯ tests/Unit/Directives/LoggerCleanDirectiveTest.php                                new_with_parentheses, fully_qualified_strict_types, blank_line_before_statement, ordered_imports  
  ⨯ tests/Unit/LoggerTest.php                                                                                   class_attributes_separation, concat_space, blank_line_before_statement  
  ⨯ tests/Unit/Services/LogBufferServiceTest.php                                                          class_attributes_separation, concat_space, not_operator_with_successor_space  
  ⨯ tests/Unit/Services/LogCleanerServiceTest.php                                                         class_attributes_separation, concat_space, not_operator_with_successor_space  
  ⨯ tests/Unit/Services/LogPathServiceTest.php                                      class_attributes_separation, function_declaration, concat_space, not_operator_with_successor_space  
  ⨯ tests/Unit/Services/LogSerializerServiceTest.php                                                                                                                      concat_space  
  ⨯ tests/Unit/Tasks/QueryLogsTaskTest.php                                               class_attributes_separation, concat_space, not_operator_with_successor_space, ordered_imports  
  ⨯ tests/Unit/Tasks/StreamLogsTaskTest.php                                              class_attributes_separation, concat_space, not_operator_with_successor_space, ordered_imports  
  ⨯ tests/Unit/Tasks/WriteLogTaskTest.php                                  class_attributes_separation, concat_space, types_spaces, not_operator_with_successor_space, ordered_imports  

