import java.io.*;

public class PHPWrapper {
    public static void main(String[] args) {
        try {
            // Command to run PHP script
            String phpCommand = "php AVIDConnections.php";

            // Create ProcessBuilder
            ProcessBuilder pb = new ProcessBuilder("bash", "-c", phpCommand);
            pb.redirectErrorStream(true);

            // Start the process
            Process process = pb.start();

            // Read output (optional)
            BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream()));
            String line;
            while ((line = reader.readLine()) != null) {
                System.out.println(line);
            }

            // Wait for the process to complete
            int exitCode = process.waitFor();

        } catch (IOException | InterruptedException e) {
            e.printStackTrace();
        }
    }
}

