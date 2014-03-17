# encoding: utf-8

# This code was received from https://github.com/bbatsov/rubocop/blob/master/lib/rubocop/formatter/progress_formatter.rb
# under the MIT license and contains modifications over the original source code.

class ScrutinizerFormatter < Rubocop::Formatter::ClangStyleFormatter
  def started(target_files)
    super
    @offenses_for_files = {}
    file_phrase = target_files.count == 1 ? 'file' : 'files'
    output.puts "Inspecting #{target_files.count} #{file_phrase}"
  end

  def file_finished(file, offenses)
    unless offenses.empty?
      count_stats(offenses)
      @offenses_for_files[file] = offenses
    end

    report_file_as_mark(file, offenses)
  end

  def finished(inspected_files)
    output.puts

    report_summary(inspected_files.count,
                   @total_offense_count,
                   @total_correction_count)
  end

  def report_file_as_mark(file, offenses)
    mark = if offenses.empty?
             green('.')
           else
             highest_offense = offenses.max do |a, b|
               a.severity_level <=> b.severity_level
             end
             colored_severity_code(highest_offense)
           end

    output.write mark
  end
end
