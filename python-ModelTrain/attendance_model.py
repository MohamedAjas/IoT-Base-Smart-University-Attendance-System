import pandas as pd

# Step 1: Load attendance.csv
attendance_df = pd.read_csv('attendance.csv')

# Step 2: Extract total classes from the last row
total_classes_row = attendance_df[attendance_df['Student_ID'] == 'Total_Classes']
total_classes = {
    'Math': total_classes_row['Math_Attended'].values[0],
    'Physics': total_classes_row['Physics_Attended'].values[0],
    'Chemistry': total_classes_row['Chemistry_Attended'].values[0],
    'Biology': total_classes_row['Biology_Attended'].values[0],
    'CS': total_classes_row['CS_Attended'].values[0]
}

# Step 3: Filter to keep only student data
attendance_df = attendance_df[attendance_df['Student_ID'] != 'Total_Classes']

# Step 4: Calculate attendance percentage for each subject
subjects = ['Math', 'Physics', 'Chemistry', 'Biology', 'CS']
for subject in subjects:
    attendance_df[f'{subject}_Percentage'] = (attendance_df[f'{subject}_Attended'] / total_classes[subject]) * 100

# Step 5: Calculate average attendance percentage across all subjects
attendance_df['Average_Percentage'] = attendance_df[[f'{subject}_Percentage' for subject in subjects]].mean(axis=1)

# Step 6: Determine eligibility (≥75% in at least one subject)
def check_eligibility(row):
    threshold = 75
    for subject in subjects:
        if row[f'{subject}_Percentage'] >= threshold:
            return 'Eligible'
    return 'Not Eligible'

attendance_df['Eligibility'] = attendance_df.apply(check_eligibility, axis=1)

# Step 7: Save results
attendance_df.to_csv('attendance_results.csv', index=False)
print("Created attendance_results.csv")

# Step 8: Display results
print("\nAttendance Results:")
print(attendance_df[['Student_ID', 'Name', 'Math_Percentage', 'Physics_Percentage', 
                    'Chemistry_Percentage', 'Biology_Percentage', 'CS_Percentage', 
                    'Average_Percentage', 'Eligibility']])

# Step 9 (Optional): Visualize attendance percentages (uncomment if matplotlib is installed)
"""
import matplotlib.pyplot as plt
attendance_df.plot(x='Name', y=['Math_Percentage', 'Physics_Percentage', 'Chemistry_Percentage', 
                               'Biology_Percentage', 'CS_Percentage', 'Average_Percentage'], 
                  kind='bar', figsize=(12, 6))
plt.axhline(y=75, color='r', linestyle='--', label='75% Threshold')
plt.title('Attendance Percentage by Subject and Average')
plt.ylabel('Percentage')
plt.legend()
plt.tight_layout()
plt.savefig('attendance_plot.png')
print("Created attendance_plot.png")
plt.show()
"""